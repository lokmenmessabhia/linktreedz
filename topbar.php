<div class="top-bar">
    <div style="display: flex; align-items: center; gap: 1rem; flex: 1;">
        <div class="search-container" style="flex: 1; max-width: 500px;">
            <i class="fas fa-magnifying-glass" style="color: var(--text-dim); font-size: 0.9rem;"></i>
            <input type="text" class="search-input" placeholder="Search assets, references, or tags..." onkeyup="searchTable(this.value)" style="width: 100%; background: transparent; border: none; color: var(--text-main); outline: none;">
        </div>
    </div>
    
    <div style="display: flex; align-items: center; gap: 1rem;">
        <!-- Action Buttons -->
        <div style="display: flex; gap: 0.6rem;">
            <button onclick="showBatchModal()" class="btn-action" title="Batch Generation">
                <i class="fa-solid fa-layer-group"></i>
            </button>
            <button onclick="showNewPageModal()" class="btn-premium" style="padding: 0.6rem 1rem; font-size: 0.85rem;">
                <i class="fa-solid fa-plus-circle"></i> New Page
            </button>
        </div>

        <?php $topbarUser = $user ?? ['username' => 'Admin']; ?>
        <div style="text-align: right; line-height: 1.2; margin-left: 0.5rem;">
            <div style="font-weight: 700; font-size: 0.9rem; color: var(--text-main);"><?php echo htmlspecialchars($topbarUser['username'] ?? 'Admin'); ?></div>
            <div style="font-size: 0.7rem; color: var(--text-dim); text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Super Admin</div>
        </div>
        <div style="width: 45px; height: 45px; border-radius: 12px; background: linear-gradient(135deg, var(--accent), var(--primary)); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; box-shadow: 0 4px 15px var(--primary-glow);">
            <?php echo strtoupper(substr(($topbarUser['username'] ?? 'Admin'), 0, 1)); ?>
        </div>
    </div>
</div>
