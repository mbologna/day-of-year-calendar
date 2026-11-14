"""The static subscription page published next to the feed."""

from __future__ import annotations

import datetime as dt
from html import escape

from . import day_stats, event_description, event_summary

TEMPLATE = """<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Day of Year Calendar</title>
    <link rel="alternate" type="text/calendar" title="Day of Year" href="{ics_url}">
    <style>
        *, *::before, *::after {{ box-sizing: border-box; }}

        body {{
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f5f5f5;
            color: #333;
            margin: 0;
            padding: 20px;
        }}

        .container {{
            max-width: 560px;
            margin: 40px auto;
            background: #fff;
            border-radius: 8px;
            padding: 32px;
            box-shadow: 0 2px 8px rgba(0,0,0,.1);
        }}

        h1 {{ margin: 0 0 24px; font-size: 1.5rem; }}
        h2 {{ margin: 24px 0 8px; font-size: 1rem; }}
        p {{ margin: 0 0 8px; font-size: .9rem; }}

        .info-box {{
            background: #f0f4ff;
            border-left: 4px solid #4a6cf7;
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 24px;
            font-size: .9rem;
        }}

        .preview {{
            background: #fff;
            border: 1px solid #d6defc;
            border-radius: 4px;
            padding: 10px 12px;
            margin-top: 10px;
        }}
        .preview-summary {{ font-weight: 600; margin-bottom: 4px; }}
        .preview-description {{ white-space: pre-line; color: #555; font-size: .85rem; }}

        .subscribe-button {{
            display: inline-block;
            background: #38a169;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            padding: 8px 18px;
            font-weight: 600;
        }}
        .subscribe-button:hover {{ background: #2f855a; }}

        .url-display {{
            background: #f7f7f7;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px 12px;
            font-family: monospace;
            font-size: .85rem;
            word-break: break-all;
            margin: 8px 0;
        }}

        .copy-button {{
            background: #4a6cf7;
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 6px 14px;
            font-size: .85rem;
            cursor: pointer;
        }}
        .copy-button:hover {{ background: #3a5ce7; }}

        .footer {{ margin-top: 24px; font-size: .8rem; color: #666; }}
    </style>
</head>
<body>
<div class="container">
    <h1>📅 Day of Year Calendar</h1>

    <div class="info-box">
        Adds an all-day event to every day showing how far through the year it is.
        Today&rsquo;s event reads:
        <div class="preview">
            <div class="preview-summary">{summary}</div>
            <div class="preview-description">{description}</div>
        </div>
    </div>

    <h2>Apple Calendar, Outlook and most desktop apps</h2>
    <p><a class="subscribe-button" href="{webcal_url}">Subscribe</a></p>

    <h2>Google Calendar and other apps</h2>
    <p>Copy this link, then use <em>Add calendar &rarr; From URL</em>. For Google Calendar,
    do this on a computer: the mobile app can&rsquo;t add calendars by URL.</p>
    <div class="url-display" id="sub-url">{ics_url}</div>
    <button class="copy-button" onclick="copyLink(this)">Copy link</button>

    <p class="footer">Preview as of {built}. The feed is rebuilt every week and always runs
    at least a year ahead.</p>
</div>
<script>
// Copy the subscription link. Where the Clipboard API is missing or refused
// (older browsers), select the link so it can be copied by hand.
function copyLink(button) {{
    const url = document.getElementById('sub-url');
    const show = (label) => {{
        button.textContent = label;
        setTimeout(() => {{ button.textContent = 'Copy link'; }}, 3000);
    }};
    const selectForManualCopy = () => {{
        const range = document.createRange();
        range.selectNodeContents(url);
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
        show('Press Ctrl+C / ⌘C');
    }};
    if (!navigator.clipboard) {{
        selectForManualCopy();
        return;
    }}
    navigator.clipboard.writeText(url.textContent).then(() => show('Copied'), selectForManualCopy);
}}
</script>
</body>
</html>
"""


def webcal_url(ics_url: str) -> str:
    """The webcal:// form of an http(s) feed URL, for the Subscribe button."""
    return "webcal://" + ics_url.split("://", 1)[1]


def render_page(today: dt.date, ics_url: str) -> str:
    """The subscription page, with today's event as the preview."""
    d = day_stats(today)
    return TEMPLATE.format(
        summary=escape(event_summary(d["day"], d["total"], d["percent"])),
        description=escape(event_description(d)),
        ics_url=escape(ics_url),
        webcal_url=escape(webcal_url(ics_url)),
        built=f"{today.day} {today:%B %Y}",
    )
