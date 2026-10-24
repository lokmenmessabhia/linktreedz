import shutil
import os

files_to_move = [
    'premium_nfc_card_mockup_1768326596149.png',
    'galaxy_template_preview_1768326873577.png',
    'neon_template_preview_1768326924849.png',
    'aurora_template_preview_1768326898986.png',
    'nfc_card_ecommerce_banner_1769176799788.png'
]

dest_dir = r'h:\xampp\htdocs\tree\uploads\order_gallery'
source_dir = r'h:\xampp\htdocs\tree'

for f in files_to_move:
    src = os.path.join(source_dir, f)
    dst = os.path.join(dest_dir, f)
    if os.path.exists(src):
        shutil.move(src, dst)
        print(f"Moved: {f}")
    else:
        print(f"Not found: {f}")
