from PIL import Image
import numpy as np
import matplotlib.pyplot as plt

def extract_curves(img_path, num_curves=11):
    image = Image.open(img_path).convert('L')  # Convert to grayscale
    image_data = np.array(image)
    img_width = image_data.shape[1]
    curve_data = {i: [] for i in range(num_curves)}

    # Process each column to detect dark pixels (assuming they are part of curves)
    for x in range(img_width):
        column = image_data[:, x]
        y_positions = np.where(column < 128)[0]  # Threshold for dark pixels

        # Limit the number of curve points to the number of curves expected
        num_found = len(y_positions)
        if num_found > num_curves:
            num_found = num_curves
        for i in range(num_found):
            curve_data[i].append((x, y_positions[i]))

    return curve_data

def plot_curves(curve_data):
    plt.figure(figsize=(10, 6))
    for i, curve in curve_data.items():
        if curve:
            x_vals, y_vals = zip(*curve)
            plt.plot(x_vals, y_vals, marker='', linestyle='-', label=f'Curve {i}')
    plt.gca().invert_yaxis()  # Invert Y-axis to match the image orientation
    plt.title('Extracted Curves')
    plt.xlabel('X coordinate')
    plt.ylabel('Y coordinate')
    plt.legend()
    plt.show()

# Path to your image file
img_path = 'Edited_Wilo-Helix_V_110.png'

# Extract and plot curves
curves = extract_curves(img_path)
plot_curves(curves)
