import os
import sys
import argparse
import logging
import time

# Set up logging
logging.basicConfig(
    filename='python_pdf_debug.log',
    level=logging.DEBUG,
    format='%(asctime)s - %(levelname)s - %(message)s'
)

try:
    import fitz  # PyMuPDF
    logging.info("PyMuPDF loaded successfully.")
except ImportError as e:
    logging.error(f"Import Error: {e}")
    print(f"Error: Missing PyMuPDF: {e}")
    sys.exit(1)

def convert_pdf_to_cmyk_stable(input_pdf, output_pdf, dpi=300):
    """
    Stable and Fast conversion of RGB PDF to CMYK.
    Passes the CMYK pixmap directly to MuPDF to avoid JPEG inversion bugs.
    """
    if not os.path.exists(input_pdf):
        print(f"Error: Input file '{input_pdf}' not found.")
        return False

    start_time = time.time()
    logging.info(f"Starting STABLE conversion: {input_pdf} -> {output_pdf}")
    
    try:
        # Open source
        src_doc = fitz.open(input_pdf)
        # Create destination
        dest_doc = fitz.open()

        for page_num in range(len(src_doc)):
            page = src_doc.load_page(page_num)
            
            # --- DPI Optimization ---
            page_rect = page.rect
            width_mm = page_rect.width * 0.3527
            height_mm = page_rect.height * 0.3527
            
            # For the 1-meter roll or very large sheets, 200 DPI is the sweet spot for print/speed
            current_dpi = dpi
            if width_mm > 600 or height_mm > 600:
                current_dpi = min(dpi, 200)
                logging.info(f"Large page ({int(width_mm)}x{int(height_mm)}mm). Using {current_dpi} DPI.")

            # Render page to CMYK Pixmap
            zoom = current_dpi / 72
            mat = fitz.Matrix(zoom, zoom)
            
            # IMPORTANT: Get pixmap as CMYK
            # We don't go through JPEG/PIL to avoid color inversion bugs in PDF viewers
            pix = page.get_pixmap(matrix=mat, colorspace=fitz.csCMYK)
            
            # Create a new page in destination
            new_page = dest_doc.new_page(width=page_rect.width, height=page_rect.height)
            
            # Insert the CMYK pixmap DIRECTLY
            # MuPDF handles the optimal storage (usually Flate) which is color-safe
            new_page.insert_image(page_rect, pixmap=pix)
            
            logging.info(f"Page {page_num + 1} finalized at {current_dpi} DPI")

        # Save with compression
        dest_doc.save(output_pdf, garbage=3, deflate=True)
        
        duration = time.time() - start_time
        logging.info(f"Conversion finished in {duration:.2f}s")
        print(f"Success: {output_pdf} ({duration:.2f}s)")
        return True

    except Exception as e:
        logging.error(f"Processing error: {e}", exc_info=True)
        print(f"Error: {e}")
        return False
    finally:
        if 'src_doc' in locals(): src_doc.close()
        if 'dest_doc' in locals(): dest_doc.close()

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Stable CMYK Converter.")
    parser.add_argument("input", help="Input RGB PDF")
    parser.add_argument("output", help="Output CMYK PDF")
    parser.add_argument("--dpi", type=int, default=300, help="DPI")

    args = parser.parse_args()
    success = convert_pdf_to_cmyk_stable(args.input, args.output, args.dpi)
    sys.exit(0 if success else 1)
