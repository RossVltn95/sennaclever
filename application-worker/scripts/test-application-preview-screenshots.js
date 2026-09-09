import fs from "node:fs/promises";
import os from "node:os";
import path from "node:path";
import { captureApplicationPreviewScreenshot } from "../src/worker.js";

const urls = [
  "https://blackstone.wd1.myworkdayjobs.com/en-US/Blackstone_Careers/details/Private-Equity-Secondaries-Analyst---Strategic-Partners_42795-1",
  "https://athene.wd5.myworkdayjobs.com/en-GB/Apollo_Careers/details/XMLNAME-2027-Summer-Analyst---Client---Product-Solutions_R255025-1",
  "https://hl.wd1.myworkdayjobs.com/en-US/Campus/details/XMLNAME-6-Month-Off-Cycle-Internship---Corporate-Finance_R3550",
];

const outDir = process.env.SFFC_PREVIEW_TEST_OUT_DIR || path.join(os.tmpdir(), "sffc-application-previews");
await fs.mkdir(outDir, { recursive: true });

for (let index = 0; index < urls.length; index += 1) {
  const url = urls[index];
  const result = await captureApplicationPreviewScreenshot(url, {
    provider: "workday",
    width: 1366,
    height: 1100,
    timeout_ms: 60000,
    settle_ms: 2200,
  });
  const filePath = path.join(outDir, `workday-preview-${index + 1}.png`);
  await fs.writeFile(filePath, result.screenshot);
  console.log(
    JSON.stringify({
      url,
      ok: result.ok,
      page_title: result.page_title,
      final_url: result.final_url,
      screenshot_path: filePath,
      bytes: result.screenshot.length,
    })
  );
}
