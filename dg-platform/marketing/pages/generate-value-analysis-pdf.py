#!/usr/bin/env python3
"""Generate DG Platform Value Analysis PDF."""

from __future__ import annotations

import html
from pathlib import Path

from fpdf import FPDF

OUT = Path(__file__).resolve().parent / "DG-Platform-Value-Analysis.pdf"

SECTIONS = [
    ("Core Platform", "~$1,325/mo market", [
        ("Contacts CRM + orgs + custom fields", "GA", "HubSpot CRM / Pipedrive", 465, "Starter $99", "No native mobile app"),
        ("Tasks + calendar + activities timeline", "GA", "Monday / Asana + Calendly", 120, "Included", "No Google Calendar sync"),
        ("Documents + universal search", "GA", "Notion + DocuSign lite", 80, "Included", "Basic attachments"),
        ("Growth Automations (single-step)", "GA", "ActiveCampaign Lite", 150, "Pro $249+", "No visual builder"),
        ("Growth Intelligence reports", "GA", "Databox / HubSpot reports", 120, "Pro $249+", "Limited custom reports"),
        ("Permissions + roles + audit log", "GA", "Enterprise CRM RBAC", 200, "Business $499+", "Audit log Enterprise only"),
        ("REST API + Dev/MCP API", "GA", "Zapier + custom API", 100, "Business $499+", "No public SDK portal"),
        ("AI Assist (OpenAI/Gemini)", "Beta", "Jasper / ChatGPT Team", 90, "Pro $249+ (BYO key)", "Requires API keys"),
    ]),
    ("Premium Pro Apps", "~$1,280/mo if stacked", [
        ("SEO Pro", "GA", "Semrush Guru + Rank Math", 390, "+$99/mo", "No rank tracking at Semrush scale"),
        ("AI Visibility Pro", "GA", "Semrush AI / Otterly-class", 310, "+$99/mo", "Prompt tracking limits"),
        ("Automation Pro", "GA", "Zapier Pro + ActiveCampaign", 220, "+$49/mo", "Visual builder not shipped"),
        ("Analytics Pro", "GA", "Databox / Looker Studio+", 130, "+$49/mo", "No ad platform connectors"),
        ("Social Pro", "Beta", "Hootsuite / Buffer", 230, "+$79/mo", "OAuth reliability varies"),
    ]),
    ("Marketing / DigitalGate Agency", "~$2,000/mo market", [
        ("Agency client CRM + pipeline", "GA", "HubSpot + agency CRM", 1380, "Growth $497+", "Single-tenant per site"),
        ("Visibility audits (PageSpeed + AI)", "GA", "Agency audit stack", 200, "Included", "Manual PDF delivery"),
        ("Voice agent (Vapi to CRM)", "GA", "Vapi + CRM integration", 150, "+$99 Voice AI", "Vapi usage extra"),
        ("Audit follow-up automations", "GA", "ActiveCampaign sequences", 150, "Included", "Template-based"),
        ("Agency email templates + digests", "GA", "Mailchimp / Klaviyo", 120, "Included", "No drag-drop builder"),
    ]),
    ("Real Estate (Roe Realty)", "~$1,200/mo market", [
        ("Properties + agents + shortcodes", "GA", "Agentbox / Rex CRM", 450, "+$99/mo", "No REA/Domain syndication"),
        ("Vendor + buyer pipelines", "GA", "VaultRE pipeline", 300, "Included", "Buyer portal not built"),
        ("Appraisal booking system", "GA", "Calendly + CRM", 80, "Included", "No SMS reminders"),
        ("Property workspace + e-sign", "Beta", "DocuSign + Dropbox", 120, "Included", "DocuSign coming soon"),
        ("Owner Portal", "Scaffold", "ListedKit / owner portals", 150, "Included (Roe)", "Placeholder Phase 2"),
        ("Pipeline reports + brochures", "GA", "Rex reporting add-ons", 100, "Included", "Limited exports"),
    ]),
    ("Accommodation (CV Hideaway)", "~$640/mo market", [
        ("Bookings + Stripe + PayID", "GA", "Lodgify + Stripe", 150, "+$99/mo", "Not all OTAs"),
        ("Guest CRM + notifications", "GA", "Guesty messaging lite", 80, "Included", "No WhatsApp/SMS"),
        ("OTA sync + iCal", "GA", "Channel manager add-on", 120, "Included", "Airbnb-focused"),
        ("Check-in QR + instructions", "GA", "Touch Stay / Hostfully", 60, "Included", "No guest mobile app"),
        ("Guest Portal", "GA", "Guest portal SaaS", 50, "Included", "Plugin-rendered"),
        ("Housekeeping + cleaning reports", "GA", "Breezeway / Turno", 100, "Included", "No staff mobile app"),
        ("Occupancy + revenue reports", "GA", "PMS analytics", 80, "Included", "No STR benchmarking"),
    ]),
    ("Creator + Preview Industry Modules", "Not resell-ready", [
        ("Creator module", "Preview", "ConvertKit + Notion", 120, "+$99/mo", "Dashboard widgets only"),
        ("Creator Studio portal", "Scaffold", "Patreon creator hub", 0, "Included", "Placeholder only"),
        ("Finance module", "Preview", "BrokerEngine / Mercury", 200, "+$99/mo", "Pipeline CRUD only"),
        ("Services module", "Preview", "ServiceM8 / Jobber", 180, "+$99/mo", "No quoting/invoicing"),
        ("Automotive module", "Preview", "DealerSocket lite", 250, "+$99/mo", "Inventory CPT only"),
        ("Commercial module", "Preview", "Re-Leased lite", 200, "+$99/mo", "Listings + pipeline only"),
    ]),
    ("Client-facing and Site Tools", "~$520/mo market", [
        ("Client Portal + onboarding + reports", "GA", "Copilot / SuiteDash", 140, "Included", "Oxygen on DigitalGate"),
        ("Stripe checkout provisioning", "GA", "Chargebee + provisioning", 150, "Included", "Manual module enable"),
        ("Live support chat", "GA", "Intercom / Crisp", 120, "Included", "No AI triage"),
        ("Site Tools", "GA", "WP Rocket + Cloudflare", 60, "Pro $249+", "WordPress only"),
        ("Reviews multi-platform", "GA", "TrustIndex", 50, "Included", "Manual import mostly"),
    ]),
]

SAVINGS = [
    ("Solo operator", 99, 680, 85),
    ("Growing team (Pro + industry)", 348, 1850, 81),
    ("Business + all Pro apps", 874, 3200, 73),
    ("Growth Systems Foundation", 497, 2200, 77),
    ("Accommodation (CVH internal)", 499, 450, -11),
    ("Real estate (Roe internal)", 598, 1100, 46),
]

PRICING_TIERS = [
    ("Starter", "$99/mo", "1", "Core CRM"),
    ("Professional", "$249/mo", "5", "Automation, reports, SEO, AI assist, 1 industry"),
    ("Business", "$499/mo", "Unlimited", "AI Visibility, API, unlimited industry"),
    ("Enterprise", "Custom", "Unlimited", "White-label, audit log, priority support"),
]

PRO_ADDONS = [
    ("SEO Pro", "+$99/mo"),
    ("AI Visibility Pro", "+$99/mo"),
    ("Automation Pro", "+$49/mo"),
    ("Analytics Pro", "+$49/mo"),
    ("Social Pro", "+$79/mo"),
    ("Industry app (each)", "+$99/mo"),
    ("Voice AI", "+$99/mo"),
    ("White Label", "+$199/mo"),
]

NEEDS_WORK = [
    "Owner Portal (Roe) - vendor reports, appraisals, docs",
    "Creator Studio (Aetherra) - content pipeline, audience CRM",
    "Buyer Portal (Roe, optional) - saved searches, offers",
    "Automation Pro - visual workflow builder",
    "RE module - REA/Domain listing syndication",
    "RE property workspace - DocuSign integration",
    "Google Search Console + Business Profile (stubs)",
    "Drag-drop email campaign builder",
    "Finance, Services, Auto, Commercial - invoicing depth",
    "Social Pro - harden OAuth across platforms",
    "SEO Pro - rank tracking at Ahrefs/Semrush scale",
    "Guest Portal - optional PWA experience",
]


class AnalysisPDF(FPDF):
    def footer(self):
        self.set_y(-12)
        self.set_font("Helvetica", "I", 8)
        self.set_text_color(120, 120, 120)
        self.cell(0, 8, f"DigitalGate DG Platform Value Analysis  |  Page {self.page_no()}", align="C")


def esc(text: str) -> str:
    return html.escape(str(text))


def build_html() -> str:
    total_market = sum(r[3] for sec in SECTIONS for r in sec[2])

    parts = [
        "<h1>DG Platform - Built Inventory and Value Analysis</h1>",
        "<p><i>DG Platform v10.44.0  |  August 2026  |  DigitalGate</i></p>",
        "<p>Conservative AUD market estimates from published list prices (HubSpot AU, Semrush/Ahrefs, "
        "Lodgify, Rex-class CRM). DG prices from plan registry and Stripe payment links.</p>",
        "<hr>",
        "<h2>Executive Summary</h2>",
        "<ul>",
        "<li><b>40+</b> built-in capabilities across <b>7</b> industry verticals</li>",
        f"<li>Full stack market equivalent: <b>~${total_market:,}/mo</b> if bought separately</li>",
        "<li>DG Business + all Premium Pro apps: <b>$874/mo</b></li>",
        "<li>Typical software savings vs best-of-breed stack: <b>53-85%</b></li>",
        "</ul>",
        "<h2>Savings by Customer Scenario</h2>",
        "<table border='1' cellpadding='4' cellspacing='0' width='100%'>",
        "<tr><th>Scenario</th><th>DG Price</th><th>Market Stack</th><th>Monthly Saving</th><th>Saving %</th></tr>",
    ]

    for label, dg, market, pct in SAVINGS:
        saved = market - dg
        saved_str = f"${saved:,}" if saved >= 0 else f"-${abs(saved):,}"
        parts.append(
            f"<tr><td>{esc(label)}</td><td>${dg}/mo</td><td>${market:,}/mo</td>"
            f"<td>{saved_str}</td><td>{pct}%</td></tr>"
        )
    parts.append("</table>")
    parts.append(
        "<p><i>Growth Systems ($497-$2,997) includes human delivery, not just software. "
        "HubSpot Marketing Pro alone starts around $1,380/mo before onboarding fees.</i></p>"
    )

    parts.append("<h2>Platform Pricing (from code)</h2>")
    parts.append("<table border='1' cellpadding='4' cellspacing='0' width='100%'>")
    parts.append("<tr><th>Tier</th><th>Price</th><th>Users</th><th>Includes</th></tr>")
    for tier, price, users, inc in PRICING_TIERS:
        parts.append(f"<tr><td>{esc(tier)}</td><td>{esc(price)}</td><td>{esc(users)}</td><td>{esc(inc)}</td></tr>")
    parts.append("</table>")

    parts.append("<h3>Premium Pro Add-ons</h3><ul>")
    for name, price in PRO_ADDONS:
        parts.append(f"<li>{esc(name)}: {esc(price)}</li>")
    parts.append("</ul>")
    parts.append("<p><b>Growth Systems:</b> Foundation $497  |  Growth $997  |  Authority $1,997  |  Partner $2,997 /mo</p>")

    for title, subtitle, rows in SECTIONS:
        section_market = sum(r[3] for r in rows)
        parts.append(f"<h2>{esc(title)}</h2>")
        parts.append(f"<p><i>{esc(subtitle)}  |  Section market est.: ${section_market:,}/mo</i></p>")
        parts.append("<table border='1' cellpadding='3' cellspacing='0' width='100%'>")
        parts.append(
            "<tr><th>Capability</th><th>Status</th><th>Market Equivalent</th>"
            "<th>AUD/mo</th><th>DG Charge</th><th>Gap</th></tr>"
        )
        for cap, status, equiv, aud, charge, gap in rows:
            aud_str = f"${aud}" if aud else "-"
            parts.append(
                f"<tr><td>{esc(cap)}</td><td>{esc(status)}</td><td>{esc(equiv)}</td>"
                f"<td>{aud_str}</td><td>{esc(charge)}</td><td>{esc(gap)}</td></tr>"
            )
        parts.append("</table>")

    parts.append("<h2>Apps Needing More Work (Priority)</h2><ol>")
    for item in NEEDS_WORK:
        parts.append(f"<li>{esc(item)}</li>")
    parts.append("</ol>")

    parts.append("<h2>Maturity Key</h2><ul>")
    parts.append("<li><b>GA</b> - Production-ready, sell today</li>")
    parts.append("<li><b>Beta</b> - Works but rough edges</li>")
    parts.append("<li><b>Preview</b> - Pipeline CRUD / demo only</li>")
    parts.append("<li><b>Scaffold</b> - Placeholder or stub</li>")
    parts.append("</ul>")

    parts.append("<h2>Bottom Line</h2>")
    parts.append(
        "<p>You have built a multi-vertical business OS - not a single SaaS category. "
        "A comparable best-of-breed stack runs ~$1,850-$3,200/mo for a growing team; "
        "DG Professional + industry ($348) or Business + all Pro apps ($874) undercuts that by 53-76% on software alone. "
        "Growth Systems at $497+ adds agency delivery where HubSpot Professional alone is ~$1,380/mo before implementation.</p>"
    )
    parts.append(
        "<p><b>Sell today:</b> Platform tiers, Pro add-ons, Real Estate + Accommodation industry apps, Growth Systems. "
        "<b>Do not oversell:</b> Owner Portal, Creator Studio, Finance/Services/Auto/Commercial depth.</p>"
    )
    parts.append("<hr><p><i>Generated from DG Platform marketing inventory. digitalgate.com.au</i></p>")

    return "\n".join(parts)


def main():
    pdf = AnalysisPDF(orientation="L", unit="mm", format="A4")
    pdf.set_auto_page_break(auto=True, margin=15)
    pdf.add_page()
    pdf.set_font("Helvetica", size=9)
    pdf.write_html(build_html())
    pdf.output(str(OUT))
    print(f"Wrote {OUT}")


if __name__ == "__main__":
    main()
