#!/usr/bin/env python3
import json
import sys
import traceback


def clean(value):
    if value is None:
        return ""
    return " ".join(str(value).replace("\u00a0", " ").split()).strip()


def as_dict(value):
    if value is None:
        return {}
    if isinstance(value, dict):
        return value
    if hasattr(value, "model_dump"):
        return value.model_dump()
    if hasattr(value, "dict"):
        return value.dict()
    if hasattr(value, "__dict__"):
        return {
            key: val
            for key, val in vars(value).items()
            if not key.startswith("_")
        }
    return {}


def list_from(value):
    if value is None:
        return []
    if isinstance(value, list):
        return value
    if isinstance(value, tuple):
        return list(value)
    return [value]


def get_any(source, keys):
    data = as_dict(source)
    for key in keys:
        if key in data and data[key] not in (None, ""):
            return data[key]
        if hasattr(source, key):
            value = getattr(source, key)
            if value not in (None, ""):
                return value
    return ""


def normalize_date(value):
    data = as_dict(value)
    if data:
        year = data.get("year") or data.get("yyyy")
        month = data.get("month") or data.get("mm")
        if year and month:
            return clean(f"{month}/{year}")
        if year:
            return clean(year)
    return clean(value)


def normalize_experience(entry):
    title = clean(get_any(entry, ["title", "job_title", "jobTitle", "position", "role"]))
    company = clean(get_any(entry, ["company", "company_name", "companyName", "organization", "employer"]))
    start = normalize_date(get_any(entry, ["start_date", "startDate", "start"]))
    end = normalize_date(get_any(entry, ["end_date", "endDate", "end"]))
    dates = clean(get_any(entry, ["dates", "date", "period", "duration"]))
    if not dates and (start or end):
        dates = clean(f"{start} - {end or 'Present'}")
    location = clean(get_any(entry, ["location", "city"]))
    bullets = []
    for key in ["responsibilities", "descriptions", "description", "bullets", "achievements"]:
        value = get_any(entry, [key])
        if isinstance(value, str):
            bullets.append(value)
        else:
            bullets.extend(list_from(value))
    bullets = [clean(item) for item in bullets if clean(item)]
    lines = [title, company, dates, location] + bullets
    return {
        "type": "experience",
        "role": title,
        "title": title,
        "company": company,
        "dates": dates,
        "startDate": start,
        "endDate": end,
        "location": location,
        "bullets": bullets,
        "lines": [clean(item) for item in lines if clean(item)],
        "parserSource": "pyresume",
        "confidence": float(get_any(entry, ["confidence", "score"]) or 0) or 0,
    }


def normalize_education(entry):
    school = clean(get_any(entry, ["school", "institution", "institute", "university", "college"]))
    degree = clean(get_any(entry, ["degree", "degree_title", "qualification", "field"]))
    start = normalize_date(get_any(entry, ["start_date", "startDate", "start"]))
    end = normalize_date(get_any(entry, ["end_date", "endDate", "end", "graduation_date", "graduationDate"]))
    dates = clean(get_any(entry, ["dates", "date", "period"]))
    if not dates and (start or end):
        dates = clean(f"{start} - {end}".strip(" -"))
    details = [clean(item) for item in list_from(get_any(entry, ["details", "descriptions", "honors"])) if clean(item)]
    return {
        "school": school,
        "degree": degree,
        "dates": dates,
        "gpa": clean(get_any(entry, ["gpa", "grade"])),
        "details": details,
        "parserSource": "pyresume",
    }


def normalize_skills(values):
    out = []
    for value in list_from(values):
        data = as_dict(value)
        if data:
            items = data.get("items") or data.get("skills") or data.get("descriptions") or []
            if items:
                out.extend(list_from(items))
            else:
                out.append(data.get("name") or data.get("label") or "")
        elif isinstance(value, str):
            out.extend(value.split(","))
        else:
            out.append(value)
    seen = set()
    clean_items = []
    for item in out:
        item = clean(item)
        key = item.lower()
        if item and key not in seen:
            seen.add(key)
            clean_items.append(item)
    return clean_items


def main():
    if len(sys.argv) < 2:
        print(json.dumps({"ok": False, "error": "missing_file"}))
        return 2

    try:
        from leverparser import ResumeParser

        parser = ResumeParser()
        resume = parser.parse(sys.argv[1])
        contact = get_any(resume, ["contact_info", "contact", "profile"]) or {}
        years = 0
        if hasattr(resume, "get_years_experience"):
            try:
                years = float(resume.get_years_experience() or 0)
            except Exception:
                years = 0

        result = {
            "ok": True,
            "parser": "pyresume",
            "profile": {
                "name": clean(get_any(contact, ["name", "full_name", "fullName"])),
                "email": clean(get_any(contact, ["email"])),
                "phone": clean(get_any(contact, ["phone", "phone_number", "phoneNumber"])),
                "linkedin": clean(get_any(contact, ["linkedin", "linkedin_url", "linkedinUrl"])),
                "location": clean(get_any(contact, ["location", "address"])),
                "summary": clean(get_any(resume, ["summary", "professional_summary", "profile_summary"])),
            },
            "experience": [
                item
                for item in (
                    normalize_experience(entry)
                    for entry in list_from(get_any(resume, ["experience", "work_experience", "workHistory"]))
                )
                if item["role"] or item["company"] or item["bullets"]
            ],
            "education": [
                item
                for item in (
                    normalize_education(entry)
                    for entry in list_from(get_any(resume, ["education", "educations"]))
                )
                if item["school"] or item["degree"] or item["details"]
            ],
            "skills": normalize_skills(get_any(resume, ["skills"])),
            "metadata": {
                "yearsExperience": years,
                "source": "leverparser",
            },
        }
        print(json.dumps(result, ensure_ascii=False))
        return 0
    except Exception as error:
        print(
            json.dumps(
                {
                    "ok": False,
                    "parser": "pyresume",
                    "error": "pyresume_failed",
                    "message": clean(str(error)),
                    "trace": traceback.format_exc(limit=3),
                }
            )
        )
        return 0


if __name__ == "__main__":
    raise SystemExit(main())
