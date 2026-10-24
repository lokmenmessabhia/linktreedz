import re
import os

def inject_csrf_to_fetch(file_path):
    if not os.path.exists(file_path):
        print(f"File not found: {file_path}")
        return

    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()

    # Pattern to find fetch calls that have headers or are POST/PUT/DELETE
    # We want to add 'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>' to the headers
    
    # Regex explanation:
    # fetch\(([^)]+?)\{([^}]+?)\}\)
    # We look for fetch(url, { ... })
    
    def add_header(match):
        args = match.group(1)
        options = match.group(2)
        
        # Check if it's potentially a state-changing request (POST, PUT, DELETE) 
        # or if it already has headers
        if any(x in options for x in ["'POST'", "\"POST\"", "'PUT'", "\"PUT\"", "'DELETE'", "\"DELETE\"", "method:"]):
            if "headers:" in options:
                # Add to existing headers
                # We look for headers: { ... }
                new_options = re.sub(r'headers:\s*\{', "headers: {\n                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>',", options)
                if new_options == options: # Fallback if regex failed
                     new_options = options.replace('headers: {', "headers: {\n                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>',")
                return f"fetch({args}{{{new_options}}})"
            else:
                # Add headers block
                # We insert headers before body or method
                new_options = "headers: {\n                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>'\n                    },\n" + options
                return f"fetch({args}{{{new_options}}})"
        return match.group(0)

    # Simpler approach: find all occurrences of fetch and replace programmatically 
    # but be careful with nested braces.
    
    # Actually, let's just use a more surgical approach for the known functions
    
    # List of functions to target in both files
    targets = [
        "performBatchGen", "generateQRCard", "performActivation", "deletePage", 
        "resetPassword", "addLink", "toggleLink", "deleteLink", "saveDesign", 
        "uploadProfileImage", "uploadBackgroundImage", "performUpdateBatch"
    ]
    
    for target in targets:
        # Look for the fetch call inside these functions
        # This is a bit complex for a one-off script, let's try a safer string replacement
        pass

    # Generic replacement for fetch calls that are likely POST/PUT/DELETE
    # Match: method: 'POST' (or PUT/DELETE)
    # Ensure it's inside a fetch
    
    # I'll use a very specific replacement for the pattern I know exists
    
    # Pattern: method: 'POST',\r?\n\s+headers: {
    content = re.sub(r"method:\s*['\"]POST['\"],\s*headers:\s*\{", 
                     "method: 'POST',\n                    headers: {\n                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>',", 
                     content)

    content = re.sub(r"method:\s*['\"]PUT['\"],\s*headers:\s*\{", 
                     "method: 'PUT',\n                    headers: {\n                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>',", 
                     content)
                     
    content = re.sub(r"method:\s*['\"]DELETE['\"],\s*headers:\s*\{", 
                     "method: 'DELETE',\n                    headers: {\n                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>',", 
                     content)

    # Handle fetch calls WITHOUT headers (like upload)
    # Look for fetch(..., { method: 'POST', body: ... })
    content = re.sub(r"fetch\(([^,]+),\s*\{\s*method:\s*['\"]POST['\"],\s*body:", 
                     r"fetch(\1, {\n                    method: 'POST',\n                    headers: {\n                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>'\n                    },\n                    body:", 
                     content)

    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(content)
    print(f"Processed: {file_path}")

inject_csrf_to_fetch(r'h:\xampp\htdocs\tree\dashboard.php')
inject_csrf_to_fetch(r'h:\xampp\htdocs\tree\portal.php')
