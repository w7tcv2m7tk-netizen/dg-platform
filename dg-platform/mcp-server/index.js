#!/usr/bin/env node

/**

 * MCP server — exposes DG Platform CRM data to Cursor (Roe Realty + DigitalGate).

 *

 * Env:

 *   DG_PLATFORM_URL  e.g. https://digitalgate.com.au or https://roerealty.com.au

 *   DG_DEV_API_KEY   from WP Admin → DG Platform → API Settings

 */



import { Server } from "@modelcontextprotocol/sdk/server/index.js";

import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";

import {

  CallToolRequestSchema,

  ListToolsRequestSchema,

} from "@modelcontextprotocol/sdk/types.js";



const BASE_URL = (process.env.DG_PLATFORM_URL || "https://digitalgate.com.au").replace(/\/$/, "");

const API_KEY = process.env.DG_DEV_API_KEY || "";



const REAL_ESTATE_TOOLS = [

  {

    name: "get_pipeline_summary",

    description: "Roe Realty: vendor/buyer pipeline, lead sources, bookings and property reports.",

    inputSchema: {

      type: "object",

      properties: {

        days: { type: "number", description: "Lookback window (default 30)" },

      },

    },

  },

  {

    name: "list_vendor_leads",

    description: "Roe Realty: list vendor acquisition leads.",

    inputSchema: {

      type: "object",

      properties: {

        status: { type: "string" },

        stage: { type: "string" },

        source: { type: "string" },

        limit: { type: "number" },

      },

    },

  },

  {

    name: "get_vendor_lead",

    description: "Roe Realty: get one vendor lead by ID.",

    inputSchema: {

      type: "object",

      properties: { id: { type: "number" } },

      required: ["id"],

    },

  },

  {

    name: "list_buyer_leads",

    description: "Roe Realty: list buyer enquiry leads.",

    inputSchema: {

      type: "object",

      properties: { limit: { type: "number" } },

    },

  },

  {

    name: "list_recent_bookings",

    description: "Roe Realty: recent appraisal/consultation bookings.",

    inputSchema: {

      type: "object",

      properties: { limit: { type: "number" } },

    },

  },

];



const MARKETING_TOOLS = [

  {

    name: "get_marketing_summary",

    description: "DigitalGate: agency clients, audits, voice leads, automation stats.",

    inputSchema: {

      type: "object",

      properties: {

        days: { type: "number", description: "Lookback window (default 30)" },

      },

    },

  },

  {

    name: "list_agency_clients",

    description: "DigitalGate: list agency CRM clients.",

    inputSchema: {

      type: "object",

      properties: {

        status: { type: "string", description: "lead, active, etc." },

        source: { type: "string" },

        limit: { type: "number" },

        offset: { type: "number" },

      },

    },

  },

  {

    name: "get_agency_client",

    description: "DigitalGate: get one agency client with contacts, notes, audits.",

    inputSchema: {

      type: "object",

      properties: { id: { type: "number" } },

      required: ["id"],

    },

  },

  {

    name: "list_voice_leads",

    description: "DigitalGate: AI voice agent lead captures.",

    inputSchema: {

      type: "object",

      properties: { limit: { type: "number" } },

    },

  },

  {

    name: "list_visibility_audits",

    description: "DigitalGate: recent agency visibility audits.",

    inputSchema: {

      type: "object",

      properties: { limit: { type: "number" } },

    },

  },

];



const ACCOMMODATION_TOOLS = [

  {

    name: "get_accommodation_summary",

    description: "DigitalGate / Currumbin Valley Hideaway: properties, bookings, guests, housekeeping, check-ins tomorrow.",

    inputSchema: {

      type: "object",

      properties: {

        days: { type: "number", description: "Lookback window (default 30)" },

      },

    },

  },

  {

    name: "list_accommodation_bookings",

    description: "DigitalGate accommodation: list bookings with optional status filter.",

    inputSchema: {

      type: "object",

      properties: {

        status: { type: "string", description: "pending, confirmed, airbnb, bookingcom, cancelled, completed" },

        limit: { type: "number" },

        offset: { type: "number" },

      },

    },

  },

  {

    name: "list_accommodation_properties",

    description: "DigitalGate accommodation: list properties with rates and housekeeping status.",

    inputSchema: { type: "object", properties: {} },

  },

  {

    name: "list_accommodation_guests",

    description: "DigitalGate accommodation: list guest CRM records.",

    inputSchema: {

      type: "object",

      properties: { limit: { type: "number" } },

    },

  },

];



const TOOLS = [...REAL_ESTATE_TOOLS, ...MARKETING_TOOLS, ...ACCOMMODATION_TOOLS];



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

    case "get_marketing_summary":

      return dgFetch("/marketing/summary", { days: args.days || 30 });

    case "list_agency_clients":

      return dgFetch("/marketing/clients", {

        status: args.status,

        source: args.source,

        limit: args.limit || 25,

        offset: args.offset || 0,

      });

    case "get_agency_client":

      if (!args.id) throw new Error("id is required");

      return dgFetch(`/marketing/clients/${args.id}`);

    case "list_voice_leads":

      return dgFetch("/marketing/voice-leads", { limit: args.limit || 25 });

    case "list_visibility_audits":

      return dgFetch("/marketing/audits", { limit: args.limit || 25 });

    case "get_accommodation_summary":

      return dgFetch("/accommodation/summary", { days: args.days || 30 });

    case "list_accommodation_bookings":

      return dgFetch("/accommodation/bookings", {

        status: args.status,

        limit: args.limit || 25,

        offset: args.offset || 0,

      });

    case "list_accommodation_properties":

      return dgFetch("/accommodation/properties");

    case "list_accommodation_guests":

      return dgFetch("/accommodation/guests", { limit: args.limit || 25 });

    default:

      throw new Error(`Unknown tool: ${name}`);

  }

}



const server = new Server(

  { name: "dg-platform", version: "1.2.0" },

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


