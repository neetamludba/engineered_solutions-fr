import numpy as np

# Points given by the user
x_points = np.array([0,    14.5,   34,  60,  122,  157, 249.25])
y_points = np.array([21.1, 31.1, 43.5, 55.8, 74.9, 82.3, 99.7])

# Generate additional x values from start to end
x_new = np.arange(0, 250)

# Perform linear interpolation
y_new = np.interp(x_new, x_points, y_points)

# Round the elements of the arrays
rounded_y = np.round(y_new, decimals=2)

# Format the output as "x: y"
output = ", ".join(f"{x}: {y}" for x, y in zip(x_new, rounded_y))

# Print the result
print(output)
