import numpy as np
from PIL import Image
import matplotlib.pyplot as plt
import cv2

def map_pixel_to_coords(pixel_coords, image_size, graph_size):
    x_scale, y_scale = graph_size
    img_height, img_width = image_size
    mapped_coords = [(x / img_width * x_scale, y / img_height * y_scale) for x, y in pixel_coords]
    return np.array(mapped_coords)

def extract_and_display_images(image_path):
    # Load and convert image to grayscale
    original_image = Image.open(image_path)
    gray_image = original_image.convert('L')
    
    # Create binary image to isolate dark curves
    threshold_value = 50
    binary_image = gray_image.point(lambda p: p > threshold_value and 255)
    
    # Convert PIL images to NumPy arrays for OpenCV processing and display
    gray_image_np = np.array(gray_image)
    binary_image_np = np.array(binary_image)
    binary_image_np = np.where(binary_image_np == 255, 0, 255).astype(np.uint8)  # Invert colors for detection
    
    # Display grayscale and binary images
    plt.figure(figsize=(12, 6))
    plt.subplot(1, 2, 1)
    plt.imshow(gray_image_np, cmap='gray')
    plt.title('Grayscale Image')
    plt.axis('off')
    
    plt.subplot(1, 2, 2)
    plt.imshow(binary_image_np, cmap='gray')
    plt.title('Binary Image (Isolated Dark Curves)')
    plt.axis('off')
    plt.show()
    
    return binary_image_np

def extract_curves(binary_image_np, image_size):
    contours, _ = cv2.findContours(binary_image_np, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    graph_size = (140, 400)
    curves_data = []
    
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
    plt.xlim(0, 140)
    plt.ylim(0, 400)
    plt.gca().invert_yaxis()
    plt.show()

# Example usage:
image_path = 'Edited_Wilo-Helix_V_110.png'  # Update with your actual path
binary_image_np = extract_and_display_images(image_path)
curves_data = extract_curves(binary_image_np, binary_image_np.shape)
plot_curves(curves_data)
