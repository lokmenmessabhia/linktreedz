import os
import re

directory = r'h:\xampp\htdocs\tree'
extensions = ['.php']

# Pattern to restore .php in internal file system calls
# Find require/include 'something' (without .php) and add it back
# Only if the file actually exists with .php
patterns = [
    re.compile(r"(require|require_once|include|include_once)\s*(['\"])([a-zA-Z0-9_-]+)(['\"])"),
]

count = 0

for root, dirs, files in os.walk(directory):
    if 'phpqrcode' in dirs: dirs.remove('phpqrcode')
    if 'fpdf' in dirs: dirs.remove('fpdf')
    
    for file in files:
        if file.endswith('.php'):
            filepath = os.path.join(root, file)
            try:
                with open(filepath, 'r', encoding='utf-8') as f:
                    content = f.read()
                
                new_content = content
                for pattern in patterns:
                    # Replace things like require_once 'config' with require_once 'config.php'
                    # But ONLY if the match doesn't already have .php
                    def repl(match):
                        func, q1, name, q2 = match.groups()
                        # Check if file exists with .php extension
                        # Assuming it's in the same directory for most of these calls
                        # or relative to the root
                        found = False
                        # Check local dir
                        if os.path.exists(os.path.join(root, name + '.php')):
                            found = True
                        # Check root dir
                        elif os.path.exists(os.path.join(directory, name + '.php')):
                            found = True
                        
                        if found:
                            return f"{func} {q1}{name}.php{q2}"
                        return match.group(0)

                    new_content = pattern.sub(repl, new_content)
                
                if new_content != content:
                    with open(filepath, 'w', encoding='utf-8') as f:
                        f.write(new_content)
                    print(f"Fixed: {filepath}")
                    count += 1
            except Exception as e:
                print(f"Error processing {filepath}: {e}")

print(f"Total files fixed: {count}")
