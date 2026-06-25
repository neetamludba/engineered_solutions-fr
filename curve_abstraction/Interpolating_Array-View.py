import numpy as np
from PIL import Image
import matplotlib.pyplot as plt
import cv2
from scipy.interpolate import interp1d

def map_pixel_to_coords(pixel_coords, image_size, graph_size):
    x_scale, y_scale = graph_size
    img_height, img_width = image_size
    mapped_coords = [(x / img_width * x_scale, y / img_height * y_scale) for x, y in pixel_coords]
    return np.array(mapped_coords)

def interpolate_curve(curve_points):
    # Assuming curve_points is sorted by x or sorts it here
    curve_points = curve_points[curve_points[:, 0].argsort()]
    x_vals, y_vals = curve_points[:, 0], curve_points[:, 1]
    
    # Create interpolation function
    interpolation_function = interp1d(x_vals, y_vals, kind='linear', fill_value='extrapolate')
    
    # Generate new x values (incrementing by 1)
    x_new = np.arange(x_vals.min(), x_vals.max())
    y_new = interpolation_function(x_new)
    
    return x_new, y_new

def process_and_extract_curves(image_path):
    # Your existing code to load image and detect curves
    original_image = Image.open(image_path)
    gray_image = original_image.convert('L')
    binary_image = gray_image.point(lambda p: p > 50 and 255)
    binary_image_np = np.array(binary_image)
    binary_image_np = np.where(binary_image_np == 255, 0, 255).astype(np.uint8)
    
    contours, _ = cv2.findContours(binary_image_np, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    curves_data = []
    for contour in contours:
        curve_points = contour.squeeze()
        if curve_points.ndim < 2:
            continue  # Skip if not enough points to form a curve
        mapped_points = map_pixel_to_coords(curve_points, binary_image_np.shape, (200, 600))
        curves_data.append(mapped_points)
    
    return curves_data

def plot_interpolated_curves(curves_data):
    plt.figure(figsize=(10, 6))
    for curve_points in curves_data:
        if len(curve_points) < 2:
            continue  # Skip curves with less than 2 points which cannot be interpolated
        x_new, y_new = interpolate_curve(curve_points)
        plt.plot(x_new, y_new, marker='o', linestyle='-')
    
    plt.title('Interpolated Curve Data')
    plt.xlabel('X coordinate (scaled)')
    plt.ylabel('Y coordinate (scaled)')
    plt.xlim(0, 200)
    plt.ylim(0, 600)
    plt.gca().invert_yaxis()
    plt.show()

# Example usage
image_path = 'Edited_Wilo-Helix_V_110.png'
curves_data = process_and_extract_curves(image_path)
plot_interpolated_curves(curves_data)
