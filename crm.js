#!/usr/bin/env node
// CLI do funil — opera o CRM pela API (local ou hospedado).
// Uso:
//   npm run crm -- list [etapa]
//   npm run crm -- show <id>
//   npm run crm -- move <id> <etapa> [--motivo "texto"] [--valor 80]
//   npm run crm -- note <id> "texto da observação"
//   npm run crm -- stats
//   npm run crm -- delete <id>
// Config via .env: CRM_URL, API_TOKEN (ou ADMIN_USER/ADMIN_PASS)

import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const envFile = path.join(__dirname, ".env");
if (fs.existsSync(envFile)) for (const line of fs.readFileSync(envFile, "utf8").split("\n")) {
  const m = line.match(/^\s*([\w.-]+)\s*=\s*(.*)?\s*$/);
  if (m && !line.trim().startsWith("#") && process.env[m[1]] === undefined) process.env[m[1]] = (m[2] || "").trim().replace(/^(['"])(.*)\1$/, "$2");
}

const BASE = (process.env.CRM_URL || "http://localhost:3000").replace(/\/$/, "");
const auth = process.env.API_TOKEN
  ? `Bearer ${process.env.API_TOKEN}`
  : `Basic ${Buffer.from(`${process.env.ADMIN_USER || "bravvo"}:${process.env.ADMIN_PASS || ""}`).toString("base64")}`;

async function api(method, p, body) {
  const r = await fetch(BASE + p, { method, headers: { Authorization: auth, "Content-Type": "application/json" }, body: body ? JSON.stringify(body) : undefined });
  const data = await r.json().catch(() => ({}));
  if (!r.ok) throw new Error(data.error || `${r.status} ${r.statusText}`);
  return data;
}

const args = process.argv.slice(2);
const cmd = args.shift();
const flag = (name) => { const i = args.indexOf(`--${name}`); return i >= 0 ? args.splice(i, 2)[1] : undefined; };
const fmtDate = (s) => new Date(s).toLocaleString("pt-BR", { day: "2-digit", month: "2-digit", hour: "2-digit", minute: "2-digit" });
const pad = (s, n) => String(s ?? "").padEnd(n).slice(0, n);

try {
  if (cmd === "list") {
    const stage = args[0];
    const leads = await api("GET", "/api/leads");
    const rows = stage ? leads.filter((l) => l.stage === stage) : leads;
    console.log(pad("id", 13) + pad("etapa", 12) + pad("criado", 13) + pad("nome", 22) + pad("whatsapp", 15) + pad("serviço", 30) + pad("deslocamento", 16) + "origem");
    for (const l of rows)
      console.log(pad(l.id, 13) + pad(l.stage, 12) + pad(fmtDate(l.created_at), 13) + pad(l.nome, 22) + pad(l.whatsapp, 15) + pad(l.servico, 30) + pad(l.deslocamento, 16) + (l.utm_campaign || l.utm_source || "-"));
    console.log(`\n${rows.length} lead(s)`);
  } else if (cmd === "show") {
    const l = await api("GET", `/api/leads/${args[0]}`);
    const { events, ...rest } = l;
    console.log(rest);
    console.log("\nHistórico:");
    for (const e of events) console.log(` ${fmtDate(e.at)}  ${e.type.padEnd(8)} ${e.from_stage ? `${e.from_stage} → ${e.to_stage}` : ""} ${e.text || ""} (${e.by})`);
  } else if (cmd === "move") {
    const motivo = flag("motivo"), valor = flag("valor");
    const [id, stage] = args;
    const l = await api("PATCH", `/api/leads/${id}`, { stage, lost_reason: motivo, valor, by: "cli" });
    console.log(`✔ ${l.nome} → ${l.stage_label}`);
  } else if (cmd === "note") {
    const [id, ...t] = args;
    await api("PATCH", `/api/leads/${id}`, { note: t.join(" "), by: "cli" });
    console.log("✔ observação adicionada");
  } else if (cmd === "stats") {
    const s = await api("GET", "/api/stats");
    console.log(s);
  } else if (cmd === "delete") {
    await api("DELETE", `/api/leads/${args[0]}`);
    console.log("✔ removido");
  } else {
    console.log(fs.readFileSync(fileURLToPath(import.meta.url), "utf8").split("\n").slice(1, 10).map((l) => l.replace(/^\/\/ ?/, "")).join("\n"));
  }
} catch (e) {
  console.error("Erro:", e.message);
  process.exit(1);
}
