<?php
// Included by day-of-year-calendar.php — displays the URL builder web interface.

date_default_timezone_set('Europe/Rome');
$today = doy_day_stats(time());
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Day of Year Calendar</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f5f5f5;
            color: #333;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 560px;
            margin: 40px auto;
            background: #fff;
            border-radius: 8px;
            padding: 32px;
            box-shadow: 0 2px 8px rgba(0,0,0,.1);
        }

        h1 { margin: 0 0 24px; font-size: 1.5rem; }

        .info-box {
            background: #f0f4ff;
            border-left: 4px solid #4a6cf7;
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 24px;
            font-size: .9rem;
        }

        .error-box {
            background: #fff0f0;
            border-left: 4px solid #e53e3e;
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 24px;
            font-size: .9rem;
        }

        .success-box {
            background: #f0fff4;
            border-left: 4px solid #38a169;
            padding: 16px;
            border-radius: 4px;
            margin-bottom: 24px;
        }

        .success-box h3 { margin: 0 0 12px; font-size: 1rem; }

        .url-display {
            background: #f7f7f7;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px 12px;
            font-family: monospace;
            font-size: .85rem;
            word-break: break-all;
            margin: 8px 0;
        }

        .preview {
            background: #fff;
            border: 1px solid #d6defc;
            border-radius: 4px;
            padding: 10px 12px;
            margin-top: 10px;
        }
        .preview-summary { font-weight: 600; margin-bottom: 4px; }
        .preview-description { white-space: pre-line; color: #555; font-size: .85rem; }

        .subscribe-button {
            display: inline-block;
            background: #38a169;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            padding: 8px 18px;
            font-weight: 600;
        }
        .subscribe-button:hover { background: #2f855a; }

        .success-box p { margin: 0 0 6px; font-size: .9rem; }
        .success-box .warning { margin: 0; font-size: .85rem; color: #555; }

        .copy-button {
            background: #4a6cf7;
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 6px 14px;
            font-size: .85rem;
            cursor: pointer;
            margin-bottom: 16px;
        }
        .copy-button:hover { background: #3a5ce7; }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            font-size: .9rem;
        }

        input[type="password"],
        input[type="text"] {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: .95rem;
        }

        input[type="submit"] {
            background: #4a6cf7;
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 10px 24px;
            font-size: 1rem;
            cursor: pointer;
            width: 100%;
        }
        input[type="submit"]:hover { background: #3a5ce7; }

        .help-text { font-size: .8rem; color: #666; margin-top: 4px; }

        .optional { color: #888; font-weight: 400; }
    </style>
</head>
<body>
<div class="container">
    <h1>📅 Day of Year Calendar</h1>

    <div class="info-box">
        Adds an all-day event to every day showing how far through the year it is.
        Today&rsquo;s event reads:
        <div class="preview">
            <div class="preview-summary"><?= htmlspecialchars(doy_event_summary($today['day'], $today['total'], $today['percent'])) ?></div>
            <div class="preview-description"><?= htmlspecialchars(doy_event_description($today)) ?></div>
        </div>
    </div>

    <?php if (isset($error)): ?>
    <div class="error-box"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (isset($subscription_url)): ?>
    <div class="success-box">
        <h3>✅ Your subscription link is ready</h3>

        <p>Apple Calendar, Outlook and most desktop apps:</p>
        <p><a class="subscribe-button" href="<?= htmlspecialchars($webcal_url) ?>">Subscribe</a></p>

        <p style="margin-top:16px">Google Calendar and other apps: copy this link, then use
        <em>Add calendar → From URL</em>.</p>
        <div class="url-display" id="sub-url"><?= htmlspecialchars($subscription_url) ?></div>
        <button class="copy-button" onclick="navigator.clipboard.writeText(document.getElementById('sub-url').textContent).then(() => { this.textContent = 'Copied'; setTimeout(() => this.textContent = 'Copy link', 2000); })">Copy link</button>

        <p class="warning">Keep this link private: it contains your access token.</p>
    </div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label for="password">Access token</label>
            <input type="password" name="password" id="password" autocomplete="current-password" required>
            <div class="help-text">The <code>AUTH_TOKEN</code> set on this server.</div>
        </div>

        <div class="form-group">
            <label for="location">Calendar label <span class="optional">(optional)</span></label>
            <input type="text" name="location" id="location"
                   value="<?= htmlspecialchars($_POST['location'] ?? '') ?>"
                   placeholder="e.g. Milan">
            <div class="help-text">Added to the calendar name, e.g. &ldquo;📅 Day of Year – Milan&rdquo;.
            Useful to tell several subscriptions apart.</div>
        </div>

        <input type="submit" name="generate_url" value="Get subscription link">
    </form>
</div>
</body>
</html>
