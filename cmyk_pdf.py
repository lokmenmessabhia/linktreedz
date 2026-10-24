import sys
import os
import argparse
from PIL import Image

def convert_images_to_cmyk_pdf(image_paths, output_pdf, dpi=300):
    """
    Converts a list of RGB images to a single CMYK PDF.
    """
    cmyk_images = []
    
    for path in image_paths:
        if not os.path.exists(path):
            print(f"Warning: File not found: {path}")
            continue
            
        try:
            img = Image.open(path)
            # Ensure it's RGB before converting to CMYK
            if img.mode != 'RGB':
                img = img.convert('RGB')
            
            # Convert to CMYK
            # Pillow's default conversion is a simple mathematical transform.
            # For better results, one might use an ICC profile, but this is the standard way.
            cmyk_img = img.convert('CMYK')
            cmyk_images.append(cmyk_img)
        except Exception as e:
            print(f"Error processing {path}: {e}")

    if not cmyk_images:
        print("Error: No valid images to process.")
        return False

    try:
        # Save images into a PDF
        # Pillow's PDF writer supports multi-page if save_all=True
        # It preserves the CMYK mode in the PDF.
        cmyk_images[0].save(
            output_pdf, 
            save_all=True, 
            append_images=cmyk_images[1:], 
            resolution=float(dpi), 
            quality=100
        )
        print(f"Successfully created: {output_pdf}")
        return True
    except Exception as e:
        print(f"Error saving PDF: {e}")
        return False

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Convert RGB images to a CMYK PDF.")
    parser.add_argument("output", help="Output PDF filename")
    parser.add_argument("images", nargs="+", help="Input image files")
    parser.add_argument("--dpi", type=int, default=300, help="DPI for the PDF (default: 300)")

    args = parser.parse_args()
    
    success = convert_images_to_cmyk_pdf(args.images, args.output, args.dpi)
    sys.exit(0 if success else 1)
