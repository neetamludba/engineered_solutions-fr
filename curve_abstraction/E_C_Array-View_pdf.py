import fitz
import numpy as np
from PIL import Image
import matplotlib.pyplot as plt
import cv2
from scipy.interpolate import interp1d

def extract_images_from_pdf(pdf_path):
    doc = fitz.open(pdf_path)
    images = []
    for page in doc:
        # Extract the image of the whole page as a raster
        pix = page.get_pixmap()
        img = Image.frombytes("RGB", [pix.width, pix.height], pix.samples)
        images.append(img.convert('L'))  # Convert directly to grayscale
    doc.close()
    return images

def process_image(img):
    # Create binary image to isolate dark curves
    threshold_value = 50
    binary_image = img.point(lambda p: p > threshold_value and 255)
    
    binary_image_np = np.array(binary_image)
    binary_image_np = np.where(binary_image_np == 255, 0, 255).astype(np.uint8)  # Invert colors for detection
    return binary_image_np

def map_pixel_to_coords(pixel_coords, image_size, graph_size):
    x_scale, y_scale = graph_size
    img_height, img_width = image_size
    mapped_coords = [(x / img_width * x_scale, y / img_height * y_scale) for x, y in pixel_coords]
    return np.array(mapped_coords)

def extract_curves_with_sorted_points(binary_image_np, image_size):
    contours, _ = cv2.findContours(binary_image_np, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    curves_data = []
    graph_size = (200, 600)
    for contour in contours:
        epsilon = 0.001 * cv2.arcLength(contour, True)
        approx = cv2.approxPolyDP(contour, epsilon, True)
        curve_points = approx.squeeze().tolist()
        if isinstance(curve_points[0], int):  # Handle single point contours
            curve_points = [curve_points]
        
        # Map points and sort by x to ensure they are always in increasing order
        mapped_points = map_pixel_to_coords(curve_points, image_size, graph_size)
        mapped_points = sorted(mapped_points, key=lambda x: x[0])  # Sort by x value
        
        # Only keep points where x is strictly increasing
        if len(mapped_points) > 1:
            filtered_points = [mapped_points[0]]
            for point in mapped_points[1:]:
                if point[0] > filtered_points[-1][0]:
                    filtered_points.append(point)
            curves_data.append(np.array(filtered_points))
        else:
            curves_data.append(np.array(mapped_points))  # For single-point contours
    return curves_data
    
def extract_curves(binary_image_np,image_size):
    contours, _ = cv2.findContours(binary_image_np, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    curves_data = []
    graph_size = (200, 600)
    for contour in contours:
        epsilon = 0.001 * cv2.arcLength(contour, True)
        approx = cv2.approxPolyDP(contour, epsilon, True)
        curve_points = approx.squeeze().tolist()
        if isinstance(curve_points[0], int):  # Handle single point contours
            curve_points = [curve_points]
        mapped_points = map_pixel_to_coords(curve_points, image_size, graph_size)
        curves_data.append(mapped_points)
    print(curves_data)
    return curves_data

def plot_curves(curves_data):
    plt.figure(figsize=(10, 6))
    for curve in curves_data:
        x_vals, y_vals = curve[:, 0], curve[:, 1]
        plt.plot(x_vals, y_vals, marker='o', linestyle='-')
    plt.title('Extracted Curve Data')
    plt.xlabel('X coordinate (scaled)')
    plt.ylabel('Y coordinate (scaled)')
    plt.xlim(0, 200)
    plt.ylim(0, 600)
    plt.gca().invert_yaxis()
    plt.show()

def main():
    pdf_path = 'abc.pdf'
    images = extract_images_from_pdf(pdf_path)
    for img in images:
        binary_image_np = process_image(img)
        plt.imshow(binary_image_np, cmap='gray')
        plt.title('Binary Image (Isolated Dark Curves)')
        plt.axis('off')
        plt.show()
        curves_data = extract_curves(binary_image_np,binary_image_np.shape)
        plot_curves(curves_data)
        # You can now plot or analyze curves_data as needed

if __name__ == "__main__":
    main()
