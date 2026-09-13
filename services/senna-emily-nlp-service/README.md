# Senna Emily NLP Service

Dependency-backed meaning engine for Emily chat routing.

It turns messy user messages into a stable meaning object that WordPress can use to decide whether to search Senna jobs, call the web-answer service, continue an application, or answer a career-advice question.

## Endpoints

- `GET /health`
- `POST /meaning`
- `POST /classify`
- `POST /rewrite`

Example:

```bash
curl -s https://your-service.up.railway.app/meaning \
  -H 'Content-Type: application/json' \
  -d '{"message":"you know emily im so tired of my job search what do you suggest","context":{"hasCv":true}}'
```

## Railway

Create a new Railway service from the `sennaclever` GitHub repo and set:

- Root Directory: `services/senna-emily-nlp-service`
- Build: Dockerfile
- Port: Railway will provide `PORT`; no manual port is needed.

Optional environment variables:

- `EMILY_NLP_TOKEN`: requires `Authorization: Bearer ...` for API calls.
- `CORS_ORIGIN`: defaults to `*`.
- `MAX_JSON_BODY`: defaults to `128kb`.

## Local Checks

```bash
npm install
npm run check
npm test
npm start
```
