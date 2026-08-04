<?php
/**
 * Property brochure print layout.
 *
 * @var array $data From DG_RE_Property_Brochure::collect()
 * @var bool  $auto_print
 */

if (!defined('ABSPATH')) {
    exit;
}

$hero = $data['images'][0] ?? '';
$gallery = array_slice($data['images'], 1, 5);
$agent = $data['agent'];
$specs = array_filter([
    ['label' => 'Bedrooms', 'value' => $data['beds']],
    ['label' => 'Bathrooms', 'value' => $data['baths']],
    ['label' => 'Car spaces', 'value' => $data['cars']],
    ['label' => 'Land', 'value' => $data['land'] !== '' && $data['land'] !== null ? $data['land'] . ' m²' : ''],
    ['label' => 'Building', 'value' => $data['building'] !== '' && $data['building'] !== null ? $data['building'] . ' m²' : ''],
    ['label' => 'Year built', 'value' => $data['year_built']],
    ['label' => 'Type', 'value' => $data['property_type']],
], static function ($item) {
    return $item['value'] !== '' && $item['value'] !== null;
});
?>
<!DOCTYPE html>
<html lang="en-AU">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($data['title']); ?> — Property Brochure</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --roe-dark: #1C2B2A;
            --roe-gold: #C9A46C;
            --roe-gold-dark: #B48B56;
            --roe-cream: #F5F2EF;
            --roe-border: #E0D6CC;
            --roe-muted: #6B7A78;
            --roe-text: #4A5B59;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--roe-cream);
            color: var(--roe-dark);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
            padding: 14px 20px;
            background: rgba(28, 43, 42, 0.96);
            backdrop-filter: blur(8px);
            border-bottom: 2px solid var(--roe-gold);
        }

        .toolbar p {
            color: #B8C5C2;
            font-size: 13px;
        }

        .toolbar-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 22px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: background 0.2s, transform 0.15s;
        }

        .btn-primary {
            background: var(--roe-gold);
            color: #fff;
        }

        .btn-primary:hover { background: var(--roe-gold-dark); }

        .btn-ghost {
            background: transparent;
            color: #fff;
            border: 1px solid rgba(255,255,255,0.25);
        }

        .btn-ghost:hover { background: rgba(255,255,255,0.08); }

        .brochure {
            max-width: 920px;
            margin: 28px auto 48px;
            background: #fff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(28, 43, 42, 0.12);
        }

        .brand-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 32px;
            background: #fff;
            border-bottom: 1px solid var(--roe-border);
        }

        .brand-bar img {
            max-height: 42px;
            width: auto;
        }

        .brand-name {
            font-family: 'Sora', sans-serif;
            font-weight: 700;
            font-size: 18px;
            color: var(--roe-dark);
            letter-spacing: -0.02em;
        }

        .brand-tag {
            font-size: 12px;
            color: var(--roe-muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .hero {
            position: relative;
            min-height: 340px;
            background: var(--roe-dark);
            overflow: hidden;
        }

        .hero img {
            width: 100%;
            height: 340px;
            object-fit: cover;
            display: block;
        }

        .hero-placeholder {
            height: 340px;
            background: linear-gradient(135deg, #1C2B2A 0%, #2d4543 50%, #1C2B2A 100%);
        }

        .hero-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(28,43,42,0.92) 0%, rgba(28,43,42,0.35) 45%, rgba(28,43,42,0.15) 100%);
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 32px;
        }

        .hero-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .hero-price {
            font-family: 'Sora', sans-serif;
            font-size: 28px;
            font-weight: 700;
            color: var(--roe-gold);
        }

        .status-pill {
            display: inline-block;
            padding: 5px 14px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #fff;
        }

        .hero h1 {
            font-family: 'Sora', sans-serif;
            font-size: clamp(1.5rem, 4vw, 2rem);
            font-weight: 800;
            color: #fff;
            line-height: 1.2;
            margin-bottom: 6px;
            letter-spacing: -0.03em;
        }

        .hero-address {
            color: #B8C5C2;
            font-size: 15px;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 1px;
            background: var(--roe-border);
            border-bottom: 1px solid var(--roe-border);
        }

        .stat {
            background: #fff;
            padding: 16px 12px;
            text-align: center;
        }

        .stat-value {
            font-family: 'Sora', sans-serif;
            font-size: 20px;
            font-weight: 700;
            color: var(--roe-dark);
            line-height: 1.2;
        }

        .stat-label {
            font-size: 11px;
            color: var(--roe-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 2px;
        }

        .content {
            padding: 32px;
        }

        .grid {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 32px;
        }

        .section {
            margin-bottom: 28px;
        }

        .section:last-child { margin-bottom: 0; }

        .section h2 {
            font-family: 'Sora', sans-serif;
            font-size: 17px;
            font-weight: 700;
            color: var(--roe-dark);
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--roe-gold);
            letter-spacing: -0.02em;
        }

        .section p {
            color: var(--roe-text);
            font-size: 14px;
            line-height: 1.75;
            white-space: pre-line;
        }

        .features {
            list-style: none;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px 16px;
        }

        .features li {
            position: relative;
            padding-left: 20px;
            font-size: 13px;
            color: var(--roe-text);
        }

        .features li::before {
            content: '';
            position: absolute;
            left: 0;
            top: 7px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--roe-gold);
        }

        .spec-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .spec-card {
            background: var(--roe-cream);
            border-radius: 12px;
            padding: 14px;
            text-align: center;
            border: 1px solid var(--roe-border);
        }

        .spec-card .value {
            font-family: 'Sora', sans-serif;
            font-size: 18px;
            font-weight: 700;
            color: var(--roe-dark);
        }

        .spec-card .label {
            font-size: 11px;
            color: var(--roe-muted);
            margin-top: 2px;
        }

        .agent-card {
            background: linear-gradient(160deg, var(--roe-dark) 0%, #243836 100%);
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            color: #fff;
        }

        .agent-photo {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--roe-gold);
            margin: 0 auto 14px;
            display: block;
        }

        .agent-initial {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            background: var(--roe-gold);
            color: var(--roe-dark);
            font-family: 'Sora', sans-serif;
            font-size: 32px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px;
        }

        .agent-name {
            font-family: 'Sora', sans-serif;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .agent-role {
            font-size: 12px;
            color: var(--roe-gold);
            font-weight: 600;
            margin-bottom: 12px;
        }

        .agent-contact {
            font-size: 13px;
            color: #B8C5C2;
            margin-bottom: 4px;
        }

        .agent-card .btn {
            margin-top: 16px;
            width: 100%;
            justify-content: center;
        }

        .inspection-box {
            background: #fff8ef;
            border: 1px solid #ecd9b8;
            border-radius: 12px;
            padding: 16px;
            font-size: 13px;
            color: var(--roe-text);
            white-space: pre-line;
        }

        .photo-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-top: 8px;
        }

        .photo-grid img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid var(--roe-border);
        }

        .floorplans {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
        }

        .floorplans img {
            width: 100%;
            border-radius: 10px;
            border: 1px solid var(--roe-border);
        }

        .footer {
            background: var(--roe-cream);
            padding: 22px 32px;
            text-align: center;
            border-top: 1px solid var(--roe-border);
            font-size: 12px;
            color: var(--roe-muted);
        }

        .footer a {
            color: var(--roe-gold-dark);
            text-decoration: none;
        }

        @media (max-width: 768px) {
            .grid { grid-template-columns: 1fr; }
            .features { grid-template-columns: 1fr; }
            .content { padding: 24px 20px; }
            .hero-overlay { padding: 24px 20px; }
            .brand-bar { padding: 16px 20px; }
        }

        @media print {
            @page { margin: 12mm; size: A4; }

            body { background: #fff; }

            .toolbar { display: none !important; }

            .brochure {
                margin: 0;
                box-shadow: none;
                border-radius: 0;
                max-width: none;
            }

            .hero { break-inside: avoid; }
            .section { break-inside: avoid; page-break-inside: avoid; }
            .agent-card { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .hero-overlay { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .status-pill { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <p>Save as PDF via your browser print dialog</p>
    <div class="toolbar-actions">
        <button type="button" class="btn btn-primary" onclick="window.print()">Save as PDF</button>
        <a href="<?php echo esc_url($data['permalink']); ?>" class="btn btn-ghost">View listing</a>
    </div>
</div>

<article class="brochure">
    <header class="brand-bar">
        <div>
            <?php if (!empty($data['logo_url'])) : ?>
                <img src="<?php echo esc_url($data['logo_url']); ?>" alt="<?php echo esc_attr($data['org_name']); ?>">
            <?php else : ?>
                <div class="brand-name"><?php echo esc_html($data['org_name']); ?></div>
            <?php endif; ?>
        </div>
        <div class="brand-tag">Property brochure</div>
    </header>

    <section class="hero">
        <?php if ($hero) : ?>
            <img src="<?php echo esc_url($hero); ?>" alt="<?php echo esc_attr($data['title']); ?>">
        <?php else : ?>
            <div class="hero-placeholder"></div>
        <?php endif; ?>
        <div class="hero-overlay">
            <div class="hero-meta">
                <span class="hero-price"><?php echo esc_html($data['price']); ?></span>
                <?php if ($data['status']) : ?>
                    <span class="status-pill" style="background:<?php echo esc_attr($data['status_color']); ?>">
                        <?php echo esc_html($data['status']); ?>
                    </span>
                <?php endif; ?>
            </div>
            <h1><?php echo esc_html($data['title']); ?></h1>
            <?php if ($data['full_address']) : ?>
                <p class="hero-address"><?php echo esc_html($data['full_address']); ?></p>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($data['beds'] || $data['baths'] || $data['cars'] || $data['land']) : ?>
    <div class="stats-row">
        <?php if ($data['beds']) : ?>
            <div class="stat"><div class="stat-value"><?php echo esc_html($data['beds']); ?></div><div class="stat-label">Bedrooms</div></div>
        <?php endif; ?>
        <?php if ($data['baths']) : ?>
            <div class="stat"><div class="stat-value"><?php echo esc_html($data['baths']); ?></div><div class="stat-label">Bathrooms</div></div>
        <?php endif; ?>
        <?php if ($data['cars']) : ?>
            <div class="stat"><div class="stat-value"><?php echo esc_html($data['cars']); ?></div><div class="stat-label">Car spaces</div></div>
        <?php endif; ?>
        <?php if ($data['land']) : ?>
            <div class="stat"><div class="stat-value"><?php echo esc_html($data['land']); ?> m²</div><div class="stat-label">Land</div></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="content">
        <div class="grid">
            <div class="col-main">
                <?php if ($data['description']) : ?>
                <section class="section">
                    <h2>About this property</h2>
                    <p><?php echo esc_html($data['description']); ?></p>
                </section>
                <?php endif; ?>

                <?php if (!empty($data['features'])) : ?>
                <section class="section">
                    <h2>Features &amp; highlights</h2>
                    <ul class="features">
                        <?php foreach ($data['features'] as $feature) : ?>
                            <li><?php echo esc_html($feature); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <?php endif; ?>

                <?php if (!empty($gallery)) : ?>
                <section class="section">
                    <h2>Gallery</h2>
                    <div class="photo-grid">
                        <?php foreach ($gallery as $img) : ?>
                            <img src="<?php echo esc_url($img); ?>" alt="">
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <?php if (!empty($data['floorplans'])) : ?>
                <section class="section">
                    <h2>Floorplans</h2>
                    <div class="floorplans">
                        <?php foreach ($data['floorplans'] as $img) : ?>
                            <img src="<?php echo esc_url($img); ?>" alt="Floorplan">
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>
            </div>

            <aside class="col-side">
                <?php if (!empty($specs)) : ?>
                <section class="section">
                    <h2>Property details</h2>
                    <div class="spec-grid">
                        <?php foreach ($specs as $spec) : ?>
                            <div class="spec-card">
                                <div class="value"><?php echo esc_html($spec['value']); ?></div>
                                <div class="label"><?php echo esc_html($spec['label']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <?php if ($data['inspection_times']) : ?>
                <section class="section">
                    <h2>Open inspections</h2>
                    <div class="inspection-box"><?php echo esc_html($data['inspection_times']); ?></div>
                </section>
                <?php endif; ?>

                <?php if ($agent['name']) : ?>
                <section class="section">
                    <h2>Your agent</h2>
                    <div class="agent-card">
                        <?php if ($agent['photo']) : ?>
                            <img class="agent-photo" src="<?php echo esc_url($agent['photo']); ?>" alt="<?php echo esc_attr($agent['name']); ?>">
                        <?php else : ?>
                            <div class="agent-initial"><?php echo esc_html(strtoupper(substr($agent['name'], 0, 1))); ?></div>
                        <?php endif; ?>
                        <div class="agent-name"><?php echo esc_html($agent['name']); ?></div>
                        <?php if ($agent['position']) : ?>
                            <div class="agent-role"><?php echo esc_html($agent['position']); ?></div>
                        <?php endif; ?>
                        <?php if ($agent['phone']) : ?>
                            <div class="agent-contact"><?php echo esc_html($agent['phone']); ?></div>
                        <?php endif; ?>
                        <?php if ($agent['email']) : ?>
                            <div class="agent-contact"><?php echo esc_html($agent['email']); ?></div>
                        <?php endif; ?>
                        <?php if ($agent['phone']) : ?>
                            <a class="btn btn-primary" href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $agent['phone'])); ?>">Call agent</a>
                        <?php endif; ?>
                    </div>
                </section>
                <?php endif; ?>
            </aside>
        </div>
    </div>

    <footer class="footer">
        <p><strong><?php echo esc_html($data['org_name']); ?></strong> · Gold Coast, Queensland · Licence No. 823646</p>
        <p><a href="<?php echo esc_url($data['permalink']); ?>"><?php echo esc_html($data['permalink']); ?></a></p>
    </footer>
</article>

<?php if (!empty($auto_print)) : ?>
<script>
window.addEventListener('load', function () {
    setTimeout(function () { window.print(); }, 600);
});
</script>
<?php endif; ?>

</body>
</html>
