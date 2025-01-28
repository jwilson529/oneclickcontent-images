import os

# Define the old and new names
old_name = "oneclickcontent-images"
new_name = "oneclickcontent-images"

# Walk through the directory
for root, dirs, files in os.walk(".", topdown=False):
    # Rename files
    for file in files:
        if old_name in file:
            old_path = os.path.join(root, file)
            new_path = os.path.join(root, file.replace(old_name, new_name))
            os.rename(old_path, new_path)
            print(f"Renamed file: {old_path} -> {new_path}")

    # Rename directories
    for dir_name in dirs:
        if old_name in dir_name:
            old_path = os.path.join(root, dir_name)
            new_path = os.path.join(root, dir_name.replace(old_name, new_name))
            os.rename(old_path, new_path)
            print(f"Renamed directory: {old_path} -> {new_path}")

print("Renaming complete!")