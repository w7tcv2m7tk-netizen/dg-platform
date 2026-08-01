#!/usr/bin/env node
/**
 * MCP server — exposes Roe Realty / DG Platform CRM data to Cursor.
 *
 * Env:
 *   DG_PLATFORM_URL  e.g. https://roerealty.com.au
 *   DG_DEV_API_KEY   from WP Admin → DG Platform → API Settings
 */

import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
} from "@modelcontextprotocol/sdk/types.js";

const BASE_URL = (process.env.DG_PLATFORM_URL || "https://roerealty.com.au").replace(/\/$/, "");
const API_KEY = process.env.DG_DEV_API_KEY || "";

const TOOLS = [
  {
    name: "get_pipeline_summary",
    description: "Pipeline overview: vendor/buyer stages, lead sources, bookings and property reports this month.",
    inputSchema: {
      type: "object",
      properties: {
        days: { type: "number", description: "Lookback window for recent activity (default 30)" },
      },
    },
  },
  {
    name: "list_vendor_leads",
    description: "List vendor acquisition leads (property report submissions, etc.).",
    inputSchema: {
      type: "object",
      properties: {
        status: { type: "string", description: "new, contacted, qualified, appointment_booked, converted, lost" },
        stage: { type: "string", description: "vendor_lead, appraisal, listing, sale, settlement, past_client" },
        source: { type: "string", description: "Lead source filter" },
        limit: { type: "number", description: "Max results (default 25)" },
      },
    },
  },
  {
    name: "get_vendor_lead",
    description: "Get a single vendor lead by ID including notes.",
    inputSchema: {
      type: "object",
      properties: {
        id: { type: "number", description: "Vendor lead ID" },
      },
      required: ["id"],
    },
  },
  {
    name: "list_buyer_leads",
    description: "List buyer enquiry leads from property pages.",
    inputSchema: {
      type: "object",
      properties: {
        limit: { type: "number", description: "Max results (default 25)" },
      },
    },
  },
  {
    name: "list_recent_bookings",
    description: "List recent property appraisal and consultation bookings.",
    inputSchema: {
      type: "object",
      properties: {
        limit: { type: "number", description: "Max results (default 20)" },
      },
    },
  },
];

async function dgFetch(path, params = {}) {
  if (!API_KEY) {
    throw new Error("DG_DEV_API_KEY is not set. Copy it from WP Admin → DG Platform → API Settings.");
  }

  const url = new URL(`${BASE_URL}/wp-json/digitalgate/v1${path}`);
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== "") {
      url.searchParams.set(key, String(value));
    }
  }

  const response = await fetch(url, {
    headers: {
      "X-API-Key": API_KEY,
      Accept: "application/json",
    },
  });

  const body = await response.text();
  let data;
  try {
    data = body ? JSON.parse(body) : {};
  } catch {
    throw new Error(`Invalid JSON from ${url.pathname}: ${body.slice(0, 200)}`);
  }

  if (!response.ok) {
    const message = data.message || data.code || response.statusText;
    throw new Error(`${url.pathname} failed (${response.status}): ${message}`);
  }

  return data;
}

async function handleTool(name, args) {
  switch (name) {
    case "get_pipeline_summary":
      return dgFetch("/leads/summary", { days: args.days || 30 });
    case "list_vendor_leads":
      return dgFetch("/leads/vendor", {
        status: args.status,
        stage: args.stage,
        source: args.source,
        limit: args.limit || 25,
      });
    case "get_vendor_lead":
      if (!args.id) throw new Error("id is required");
      return dgFetch(`/leads/vendor/${args.id}`);
    case "list_buyer_leads":
      return dgFetch("/leads/buyer", { limit: args.limit || 25 });
    case "list_recent_bookings":
      return dgFetch("/bookings/recent", { limit: args.limit || 20 });
    default:
      throw new Error(`Unknown tool: ${name}`);
  }
}

const server = new Server(
  { name: "dg-platform", version: "1.0.0" },
  { capabilities: { tools: {} } }
);

server.setRequestHandler(ListToolsRequestSchema, async () => ({ tools: TOOLS }));

server.setRequestHandler(CallToolRequestSchema, async (request) => {
  try {
    const data = await handleTool(request.params.name, request.params.arguments || {});
    return {
      content: [{ type: "text", text: JSON.stringify(data, null, 2) }],
    };
  } catch (error) {
    return {
      content: [{ type: "text", text: error instanceof Error ? error.message : String(error) }],
      isError: true,
    };
  }
});

const transport = new StdioServerTransport();
await server.connect(transport);
