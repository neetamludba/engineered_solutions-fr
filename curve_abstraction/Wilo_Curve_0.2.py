from PIL import Image
import matplotlib.pyplot as plt
import numpy as np
from scipy.ndimage import label, find_objects

# Load the image
image_path = 'Edited_Wilo-Helix_V_110.png'
img = Image.open(image_path)

# Convert the image to grayscale
gray_img = img.convert('L')

# Convert the grayscale image to a numpy array
img_array = np.array(gray_img)

# Threshold the image to isolate the curves (modify threshold_value as needed)
threshold_value = 50
binary_image = img_array < threshold_value

# Label connected components
labeled_array, num_features = label(binary_image)

# Extract each curve using the labeled features
curve_slices = find_objects(labeled_array)
curve_data = []
for curve_slice in curve_slices:
    curve_pixels = np.where(labeled_array[curve_slice] == labeled_array[curve_slice].max())
    curve_points = np.column_stack((curve_pixels[1] + curve_slice[1].start, curve_pixels[0] + curve_slice[0].start))
    curve_data.append(curve_points)

# Define the image size and scale based on the user's input
graph_width_units = 200  # x-axis units
graph_height_units = 600  # y-axis units
image_width_pixels, image_height_pixels = binary_image.shape[1], binary_image.shape[0]
scale_x = graph_width_units / image_width_pixels
scale_y = graph_height_units / image_height_pixels

# Apply the transformation
transformed_curve_data = []
for curve_points in curve_data:
    transformed_points = curve_points * np.array([scale_x, scale_y])
    transformed_curve_data.append(transformed_points)

# Plot the transformed curves
plt.figure(figsize=(10, 6))
for curve_points in transformed_curve_data:
    plt.plot(curve_points[:, 0], curve_points[:, 1], marker='o', linestyle='-')
plt.title('Transformed Curve Data on the Graph Scale')
plt.xlabel('X units')
plt.ylabel('Y units')
plt.gca().invert_yaxis()  # Invert the y-axis to match the original graph orientation
plt.show()
