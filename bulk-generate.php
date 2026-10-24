<?php
require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';
require_once 'security.php';

$auth = new Auth();
$auth->requireAuth();
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Token Engine | Linky Dz</title>
    <link rel="icon" type="image/png" href="logolinky.png">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&family=Outfit:wght@300;400;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard-style.css?v=<?php echo time(); ?>">
    <style>
        .progress-log {
            background: #0f172a;
            border: 1px solid var(--glass-border);
            border-radius: 1rem;
            height: 500px;
            overflow-y: auto;
            padding: 1.5rem;
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            font-size: 0.85rem;
            box-shadow: inset 0 2px 10px rgba(0,0,0,0.2);
            color: #94a3b8;
        }
        .log-entry { margin-bottom: 0.5rem; padding: 0.6rem; border-radius: 0.5rem; transition: background 0.2s; border-left: 3px solid transparent; }
        .log-entry.success { background: rgba(34, 197, 94, 0.1); color: #4ade80; border-left-color: #22c55e; }
        .log-entry.error { background: rgba(239, 68, 68, 0.1); color: #f87171; border-left-color: #ef4444; }
        .log-entry.info { background: rgba(59, 130, 246, 0.1); color: #60a5fa; border-left-color: #3b82f6; }
        .log-entry.working { background: rgba(245, 158, 11, 0.1); color: #fbbf24; border-left-color: #f59e0b; }

        .option-group { margin-bottom: 1.5rem; }
        .option-label { display: block; font-weight: 700; font-size: 0.85rem; color: var(--text-dim); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.05em; }
        
        .template-selector {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
            margin-top: 1rem;
        }
        .template-chip {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.8rem;
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .template-chip input { display: none; }
        .template-chip:hover { border-color: var(--primary); background: rgba(0, 133, 63, 0.05); }
        .template-chip.active { border-color: var(--primary); background: rgba(0, 133, 63, 0.1); color: var(--primary); }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <main class="main">
        <?php include 'topbar.php'; ?>
        
        <div class="container-fluid" style="padding: 1.5rem; max-width: 1400px;">
            <div class="header" style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: flex-end;">
                <div class="header-title">
                    <a href="dashboard" class="btn-back" style="margin-bottom: 0.5rem;">
                        <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <h1 style="font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 2.2rem; margin: 0;">Bulk Generation Engine</h1>
                    <p style="color: var(--text-dim); margin-top: 0.5rem;">Create secure NFC tokens and QR codes at scale with automated profiles.</p>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1.8fr; gap: 2rem; margin-bottom: 2.5rem;">
                <!-- Configuration Panel -->
                <div class="glass-card" style="padding: 2rem; height: fit-content;">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem;">
                        <div style="width: 12px; height: 12px; border-radius: 50%; background: #22c55e; box-shadow: 0 0 10px #22c55e;"></div>
                        <h3 style="font-family: 'Outfit'; font-weight: 800; margin: 0;">Engine Configuration</h3>
                    </div>

                    <form id="genForm">
                        <div class="option-group">
                            <label class="option-label">Tokens to Generate</label>
                            <div style="position: relative;">
                                <input type="number" name="count" class="input-glass" value="100" min="1" max="500" style="padding-left: 3rem;">
                                <i class="fa-solid fa-hashtag" style="position: absolute; left: 1.2rem; top: 50%; transform: translateY(-50%); color: var(--text-dim);"></i>
                            </div>
                        </div>

                        <div class="option-group">
                            <label class="option-label">Batch Reference (Optional)</label>
                            <div style="position: relative;">
                                <input type="text" name="reference" class="input-glass" placeholder="e.g., Algiers_Retail_01" style="padding-left: 3rem;">
                                <i class="fa-solid fa-folder-tree" style="position: absolute; left: 1.2rem; top: 50%; transform: translateY(-50%); color: var(--text-dim);"></i>
                            </div>
                        </div>

                        <div class="option-group">
                            <label class="option-label">Pre-activate Social links</label>
                            <p style="font-size: 0.75rem; color: var(--text-dim); margin-bottom: 1rem;">Selected links will be added automatically to each generated profile.</p>
                            
                            <div class="template-selector">
                                <label class="template-chip">
                                    <input type="checkbox" name="templates[]" value="facebook">
                                    <i class="fab fa-facebook"></i> Facebook
                                </label>
                                <label class="template-chip">
                                    <input type="checkbox" name="templates[]" value="instagram">
                                    <i class="fab fa-instagram"></i> Instagram
                                </label>
                                <label class="template-chip">
                                    <input type="checkbox" name="templates[]" value="tiktok">
                                    <i class="fab fa-tiktok"></i> TikTok
                                </label>
                                <label class="template-chip">
                                    <input type="checkbox" name="templates[]" value="review">
                                    <i class="fa-solid fa-star"></i> Google Review
                                </label>
                            </div>
                        </div>

                        <div style="margin-top: 3rem;">
                            <button type="submit" id="startBtn" class="btn-premium" style="width: 100%; height: 60px; font-size: 1.15rem;">
                                <i class="fa-solid fa-bolt-lightning"></i> Execute Bulk Generation
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Progress Terminal -->
                <div class="glass-card" style="padding: 2rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h3 style="font-family: 'Outfit'; font-weight: 800; margin: 0;">Live Generation Progress</h3>
                        <div id="statusBadge" class="status-badge status-pending" style="font-size: 0.7rem; padding: 0.4rem 0.8rem;">Awaiting Init</div>
                    </div>
                    <div class="progress-log" id="log">
                        <div class="log-entry info"><i class="fa-solid fa-terminal"></i> Ready for execution. Configure options and click start.</div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        // UI Logic for Chips
        document.querySelectorAll('.template-chip').forEach(chip => {
            chip.addEventListener('click', () => {
                const input = chip.querySelector('input');
                setTimeout(() => {
                    chip.classList.toggle('active', input.checked);
                }, 10);
            });
        });

        function log(message, type = 'info') {
            const el = document.getElementById('log');
            const div = document.createElement('div');
            div.className = 'log-entry ' + type;
            const icon = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-triangle',
                info: 'fa-circle-info',
                working: 'fa-spinner fa-spin'
            }[type] || 'fa-info-circle';

            div.innerHTML = `<i class="fa-solid ${icon}"></i> <span>${message}</span>`;
            el.appendChild(div);
            el.scrollTop = el.scrollHeight;
        }

        document.getElementById('genForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('startBtn');
            const statusBadge = document.getElementById('statusBadge');
            const formData = new FormData(this);
            const data = {
                count: formData.get('count'),
                reference: formData.get('reference'),
                templates: Array.from(formData.getAll('templates[]'))
            };

            if (!confirm(`Are you sure you want to generate ${data.count} tokens? This process cannot be undone.`)) return;

            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating...';
            statusBadge.className = 'status-badge status-shipping'; // Use existing class for orange
            statusBadge.innerText = 'PROCESSING';
            
            document.getElementById('log').innerHTML = '';
            log(`Initializing Batch Generation: ${data.count} tokens...`, 'info');
            log(`Batch Reference: ${data.reference || 'SYSTEM_AUTO'}`, 'info');
            log(`Preprocessing Social Templates: ${data.templates.join(', ') || 'None'}`, 'working');

            try {
                const response = await fetch('api-generate-batch.php', {
                    method: 'POST',
                    body: JSON.stringify(data),
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>'
                    }
                });

                const result = await response.json();

                if (result.success) {
                    log(`Batch complete! Successfully generated ${result.generated}/${result.total} cards.`, 'success');
                    if (result.errors && result.errors.length > 0) {
                        result.errors.forEach(err => log(err, 'error'));
                    }
                    statusBadge.className = 'status-badge status-delivered';
                    statusBadge.innerText = 'COMPLETED';
                } else {
                    log(`Generation failed: ${result.error}`, 'error');
                    statusBadge.className = 'status-badge status-cancelled';
                    statusBadge.innerText = 'FAILED';
                }
            } catch (err) {
                log(`Network error: ${err.message}`, 'error');
                statusBadge.className = 'status-badge status-cancelled';
                statusBadge.innerText = 'ERROR';
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-bolt-lightning"></i> Execute Bulk Generation';
            }
        });
        </script>
    </main>
</body>
</html>
