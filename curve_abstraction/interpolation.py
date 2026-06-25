import numpy as np
from scipy.interpolate import CubicSpline

# Points given by the user
x_points = np.array([0,   10, 20, 40, 60, 80, 96.6])
y_points = np.array([492, 495, 492, 464, 403, 302, 191])

# Create a cubic spline interpolation of the given points
cs = CubicSpline(x_points, y_points)

# Generate additional x values from start to end
x_new = np.arange(0, 98)

# Calculate the interpolated y values for these new x values
y_new = cs(x_new)
# Round the elements of the arrays
rounded_y = np.round(y_new, decimals=2)
# x = []
# y=[]
# x.append(x_new)
# y.append(rounded_y)
# Print rounded x and y values
np.set_printoptions(threshold=np.inf)

# print(x)
rounded_y_str = ', '.join(map(str, rounded_y))

# Print the comma-separated values
print(rounded_y_str)