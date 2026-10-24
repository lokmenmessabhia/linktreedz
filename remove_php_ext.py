import os
import re

directory = r'h:\xampp\htdocs\tree'
extensions = ['.php', '.js']
exclude_dirs = ['fpdf', 'phpqrcode', '.well-known']

# Pattern to find .php extension in links/redirects
# Matches .php" or .php' or .php?
pattern = re.compile(r'\.php([\'"\?])')

count = 0

for root, dirs, files in os.walk(directory):
    # Skip excluded directories
    dirs[:] = [d for d in dirs if d not in exclude_dirs]
    
    for file in files:
        if any(file.endswith(ext) for ext in extensions):
            filepath = os.path.join(root, file)
            try:
                with open(filepath, 'r', encoding='utf-8') as f:
                    content = f.read()
                
                new_content = pattern.sub(r'\1', content)
                
                if new_content != content:
                    with open(filepath, 'w', encoding='utf-8') as f:
                        f.write(new_content)
                    print(f"Updated: {filepath}")
                    count += 1
            except Exception as e:
                print(f"Error processing {filepath}: {e}")

print(f"Total files updated: {count}")
