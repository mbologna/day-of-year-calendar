<?php
// Included by day-of-year-calendar.php — displays the URL builder web interface.
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
        input[type="text"],
        select {
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

        .footer { margin-top: 24px; font-size: .8rem; color: #888; border-top: 1px solid #eee; padding-top: 16px; }
    </style>
</head>
<body>
<div class="container">
    <h1>📅 Day of Year Calendar</h1>

    <div class="info-box">
        Generates an all-day calendar event for each day showing its position in the year
        (e.g. <strong>124/365 · 34%</strong>). Enter your password to build a subscription URL.
    </div>

    <?php if (isset($error)): ?>
    <div class="error-box"><strong>Error:</strong> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (isset($subscription_url)): ?>
    <div class="success-box">
        <h3>✅ Subscription URL Generated</h3>

        <p style="margin:0 0 6px;font-weight:600;font-size:.9rem;">HTTPS URL:</p>
        <div class="url-display" id="sub-url"><?= htmlspecialchars($subscription_url) ?></div>
        <button class="copy-button" onclick="navigator.clipboard.writeText(document.getElementById('sub-url').textContent)">Copy</button>

        <p style="margin:0 0 6px;font-weight:600;font-size:.9rem;">Webcal URL (recommended):</p>
        <div class="url-display" id="webcal-url"><?= htmlspecialchars($webcal_url) ?></div>
        <button class="copy-button" onclick="navigator.clipboard.writeText(document.getElementById('webcal-url').textContent)">Copy</button>

        <hr style="border:none;border-top:1px solid #c6f6d5;margin:16px 0">
        <p style="margin:0;font-size:.85rem;"><strong>To subscribe in most calendar apps:</strong> copy the Webcal URL,
        then use <em>Add calendar → From URL</em>.</p>
    </div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label for="password">Password <span style="color:#e53e3e">*</span></label>
            <input type="password" name="password" id="password" required>
        </div>

        <div class="form-group">
            <label for="zone">Timezone</label>
            <select name="zone" id="zone">
                <?php
                $current_zone = $_POST['zone'] ?? 'Europe/Rome';
                foreach (timezone_identifiers_list() as $tz):
                    $selected = $tz === $current_zone ? ' selected' : '';
                    echo '<option value="' . htmlspecialchars($tz) . '"' . $selected . '>'
                        . htmlspecialchars($tz) . "</option>\n";
                endforeach;
                ?>
            </select>
        </div>

        <div class="form-group">
            <label for="location">Location Name <span style="color:#888;font-weight:400">(optional)</span></label>
            <input type="text" name="location" id="location"
                   value="<?= htmlspecialchars($_POST['location'] ?? '') ?>"
                   placeholder="e.g., Milan">
            <div class="help-text">Shown in the calendar title: "📅 Day of Year – Milan"</div>
        </div>

        <input type="submit" name="generate_url" value="Generate Subscription URL">
    </form>

    <div class="footer">
        Events show day number, percentage complete, days remaining, and ISO week number.
    </div>
</div>
</body>
</html>
