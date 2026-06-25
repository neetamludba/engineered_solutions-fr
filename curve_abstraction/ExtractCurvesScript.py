from PIL import Image
import numpy as np
import matplotlib.pyplot as plt
import cv2


# Load the image
image_path = 'Edited_Wilo-Helix_V_110.png'  # Update this with the actual image path
original_image = Image.open(image_path)

# Convert image to grayscale and enhance to isolate dark curves
gray_image = original_image.convert('L')

threshold_value = 50
binary_image = gray_image.point(lambda p: p > threshold_value and 255)

# Prepare for curve detection
binary_image_np = np.array(binary_image)
binary_image_np = np.where(binary_image_np == 255, 0, 255).astype(np.uint8)  # Invert colors

# Detect curves
contours, _ = cv2.findContours(binary_image_np, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)

# Function to map pixel coordinates to graph coordinates
def map_pixel_to_coords(pixel_coords, image_size, graph_size):
    x_scale, y_scale = graph_size
    img_height, img_width = image_size
    mapped_coords = [(x / img_width * x_scale, y / img_height * y_scale) if isinstance(x, int) else (x[0] / img_width * x_scale, x[1] / img_height * y_scale) for x,y in pixel_coords]
    return mapped_coords

# Process and plot each contour
graph_size = (200, 600)
plt.figure(figsize=(10, 6))
for contour in contours:
    epsilon = 0.001 * cv2.arcLength(contour, True)
    approx = cv2.approxPolyDP(contour, epsilon, True)
    curve_points = approx.squeeze().tolist()
    if isinstance(curve_points[0], int):  # Handle single point contours
        curve_points = [curve_points]
    mapped_points = map_pixel_to_coords(curve_points, binary_image_np.shape[:2], graph_size)
    x_vals, y_vals = zip(*mapped_points)
    plt.plot(x_vals, y_vals, marker='o', linestyle='-')

plt.title('Extracted Curve Data')
plt.xlabel('X coordinate (scaled)')
plt.ylabel('Y coordinate (scaled)')
plt.xlim(0, 200)
plt.ylim(0, 600)
plt.gca().invert_yaxis()
plt.show()

