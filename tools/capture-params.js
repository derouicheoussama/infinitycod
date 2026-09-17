const browserPluginRoot =
  process.env.ZCODE_PLUGIN_ROOT ?? process.env.CLAUDE_PLUGIN_ROOT;
if (!browserPluginRoot) {
  throw new Error("Browser plugin root is unavailable");
}
const { join } = await import("node:path");
const { pathToFileURL } = await import("node:url");
const browserClientUrl = pathToFileURL(
  join(browserPluginRoot, "scripts", "browser-client.mjs"),
).href;
const { setupBrowserRuntime } = await import(browserClientUrl);
await setupBrowserRuntime({ globals: globalThis });
const browser = await agent.browsers.getForUrl("http://127.0.0.1:8090/?p=13");
const list = await browser.tabs.list();
const target = list.find(t => (t.url || "").indexOf("8090") !== -1);
const tab = await browser.tabs.get(target.id);
const params = await tab.playwright.evaluate(`(() => {
  var form = document.querySelector('#icod-preview-card').closest('form');
  var p = new URLSearchParams(new FormData(form));
  p.set('action', 'icod_preview_form');
  p.set('product_id', '13');
  return p.toString();
})()`);
console.log(params);
