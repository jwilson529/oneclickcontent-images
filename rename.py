#!/usr/bin/env python3
import os
import re

# --- Configuration ---
# Mapping for file/directory names (case-sensitive lower-case replacements)
# Replace "oneclickcontent-images" (or with underscore) with "occidg".
name_replacements = {
    "oneclickcontent-images": "occidg",
    "oneclickcontent_images": "occidg",
}

# Mapping for file contents.
# For content, we want to handle both lower-case and CamelCase variants.
content_replacements = [
    # Replace the CamelCase version (e.g., for functions/classes)
    (r"OneClickContent_Images_", "Occidg_"),
    # Replace the lower-case dash version
    (r"oneclickcontent-images", "occidg"),
    # Replace the lower-case underscore version
    (r"oneclickcontent_images", "occidg"),
]

# Extensions of files to process (skip binary files like images, zip, etc.)
TEXT_EXTENSIONS = {'.php', '.js', '.css', '.json', '.txt', '.md', '.html', '.pot'}

# --- Functions ---
def rename_item(old_path):
    """
    Rename a file or directory if its name contains any of the target substrings.
    """
    dirname, basename = os.path.split(old_path)
    new_basename = basename
    for old, new in name_replacements.items():
        if old in new_basename:
            new_basename = new_basename.replace(old, new)
    new_path = os.path.join(dirname, new_basename)
    if new_path != old_path:
        print(f"Renaming: {old_path} -> {new_path}")
        os.rename(old_path, new_path)
        return new_path
    return old_path

def process_file_contents(filepath):
    """
    Open a text file and perform search/replace on its contents.
    """
    # Only process files with allowed extensions.
    ext = os.path.splitext(filepath)[1].lower()
    if ext not in TEXT_EXTENSIONS:
        return
    try:
        with open(filepath, "r", encoding="utf-8") as f:
            contents = f.read()
    except Exception as e:
        print(f"Skipping {filepath}: {e}")
        return

    original = contents
    for pattern, replacement in content_replacements:
        contents = re.sub(pattern, replacement, contents)
    if contents != original:
        print(f"Updating content in: {filepath}")
        with open(filepath, "w", encoding="utf-8") as f:
            f.write(contents)

def process_directory(root):
    """
    Walk the directory tree, rename files/directories and process file contents.
    """
    # Walk from the bottom up so that directory renames don't affect the walk.
    for dirpath, dirnames, filenames in os.walk(root, topdown=False):
        # Process files.
        for filename in filenames:
            full_path = os.path.join(dirpath, filename)
            new_full_path = rename_item(full_path)
            process_file_contents(new_full_path)
        # Process directories.
        for dirname in dirnames:
            full_dir_path = os.path.join(dirpath, dirname)
            rename_item(full_dir_path)

# --- Main ---
if __name__ == '__main__':
    root_dir = os.getcwd()  # run in the current directory
    print(f"Processing directory: {root_dir}")
    process_directory(root_dir)
    print("Renaming and replacement complete.")