# Bulk Car Upload - Complete Field Reference

## ✅ **DEALERSHIP ROLE ACCESS CONFIRMED**
The system now allows users with the **'dealership'** role to perform bulk uploads.

## 📋 **ALL AVAILABLE FIELDS IN CSV TEMPLATE**

### **REQUIRED FIELDS** (Must be filled)
| CSV Column | Form Field | Data Type | Example |
|------------|------------|-----------|---------|
| Make | Make | Select | BMW |
| Model | Model | Select | X5 |
| Year | Year | Select | 2020 |
| Mileage | Mileage | Number | 50000 |
| Price | Price | Number | 35000 |
| Engine Capacity | Engine Capacity | Select | 3.0 |
| Fuel Type | Fuel Type | Select | Diesel |
| Transmission | Transmission | Select | Automatic |
| Body Type | Body Type | Select | SUV |
| Drive Type | Drive Type | Select | All-Wheel Drive |
| Exterior Color | Exterior Color | Select | Black |
| Interior Color | Interior Color | Select | Black |
| Number of Doors | Number of Doors | Select | 5 |
| Number of Seats | Number of Seats | Select | 5 |
| Number of Owners | Number of Owners | Number | 1 |
| Description | Description | Text | Excellent condition, full service history |

### **OPTIONAL FIELDS**
| CSV Column | Form Field | Data Type | Example |
|------------|------------|-----------|---------|
| City | City | Text | Dublin |
| District | District | Text | Dublin City |
| Address | Address | Text | 123 Main Street, Dublin |
| Latitude | Latitude | Number | 53.3498 |
| Longitude | Longitude | Number | -6.2603 |
| Availability | Availability | Select | In Stock |
| Horsepower | Horsepower | Number | 265 |
| MOT Until | MOT Until | Select | 2025-06 |
| Is Antique | Is Antique | Boolean | 0 |

### **ARRAY FIELDS** (Comma-separated values)
| CSV Column | Form Field | Data Type | Example |
|------------|------------|-----------|---------|
| Vehicle History | Vehicle History | Checkboxes | no_accidents,regular_maintenance,clear_title |
| Extras | Extras | Checkboxes | leather_seats,sunroof,parking_sensors |

## 🎯 **VALID VALUES FOR SELECT FIELDS**

### **Fuel Type Options:**
- Petrol
- Diesel
- Electric
- Petrol hybrid
- Diesel hybrid
- Plug-in petrol
- Plug-in diesel
- Bi Fuel
- Hydrogen
- Natural Gas

### **Transmission Options:**
- Automatic
- Manual

### **Body Type Options:**
- Hatchback
- Saloon
- Coupe
- Convertible
- Estate
- SUV
- MPV
- Pickup
- Camper
- Minibus
- Limousine
- Car Derived Van
- Combi Van
- Panel Van
- Window Van

### **Drive Type Options:**
- Front-Wheel Drive
- Rear-Wheel Drive
- All-Wheel Drive
- 4-Wheel Drive

### **Exterior Color Options:**
- Black
- White
- Silver
- Gray
- Red
- Blue
- Green
- Yellow
- Brown
- Beige
- Orange
- Purple
- Gold
- Bronze

### **Interior Color Options:**
- Black
- Gray
- Beige
- Brown
- White
- Red
- Blue
- Tan
- Cream

### **Availability Options:**
- In Stock
- In Transit

### **Door Options:**
- 0, 2, 3, 4, 5, 6, 7

### **Seat Options:**
- 1, 2, 3, 4, 5, 6, 7, 8

### **MOT Until Options:**
- Expired
- 2024-12, 2025-01, 2025-02, etc. (Format: YYYY-MM)

## 🔧 **VEHICLE HISTORY VALUES** (for comma-separated field)
- no_accidents
- minor_accidents
- major_accidents
- regular_maintenance
- engine_overhaul
- transmission_replacement
- repainted
- bodywork_repair
- rust_treatment
- no_modifications
- performance_upgrades
- cosmetic_modifications
- flood_damage
- fire_damage
- hail_damage
- clear_title
- no_known_issues
- odometer_replacement

## ⭐ **EXTRAS VALUES** (for comma-separated field)
- alloy_wheels
- cruise_control
- disabled_accessible
- keyless_start
- rear_view_camera
- start_stop
- sunroof
- heated_seats
- android_auto
- apple_carplay
- folding_mirrors
- leather_seats
- panoramic_roof
- parking_sensors
- camera_360
- adaptive_cruise_control
- blind_spot_mirror
- lane_assist
- power_tailgate

## 📝 **EXAMPLE CSV ROW**
```csv
Make,Model,Year,Mileage,Price,Description,City,District,Address,Latitude,Longitude,Availability,Engine Capacity,Fuel Type,Transmission,Drive Type,Horsepower,Body Type,Number of Doors,Number of Seats,Exterior Color,Interior Color,MOT Until,Number of Owners,Is Antique,Vehicle History,Extras
BMW,X5,2020,50000,35000,"Excellent condition, full service history",Dublin,Dublin City,"123 Main Street, Dublin",53.3498,-6.2603,In Stock,3.0,Diesel,Automatic,All-Wheel Drive,265,SUV,5,5,Black,Black,2025-06,1,0,"no_accidents,regular_maintenance,clear_title","leather_seats,sunroof,parking_sensors"
```

## 🚀 **BULK UPLOAD PROCESS**

### **Step 1: Access** 
- Go to `yoursite.com/bulk-import/`
- Must be logged in with Administrator, Dealer, or **Dealership** role

### **Step 2: Download Template**
- Click "Download CSV Template"
- Template includes all fields with proper headers
- Includes example row with correct formatting

### **Step 3: Fill Data**
- Use exact values from the lists above
- For array fields (Vehicle History, Extras): separate with commas
- For boolean fields (Is Antique): use 1 for true, 0 for false

### **Step 4: Upload**
- Save as CSV format
- Upload via the form
- System will process and report results

## ⚠️ **IMPORTANT NOTES**

1. **Images**: Bulk import does NOT support images - add individually after import
2. **Status**: All imported cars are set to "Pending" for review
3. **Validation**: Missing required fields will cause import to fail for that row
4. **Location**: Latitude/Longitude can be left empty if not known
5. **Arrays**: Use underscore format for Vehicle History and Extras values
6. **Year Range**: 1948-2025 supported
7. **Engine Capacity**: 0.4-12.0 supported (increments of 0.1)

## 🎯 **DEALERSHIP ROLE PERMISSIONS**
- ✅ Can access bulk import page
- ✅ Can download CSV template
- ✅ Can upload and process CSV files
- ✅ Can use quick-add templates
- ✅ Full access to all bulk features

Your bulk upload system is now **FULLY CONFIGURED** with all form fields included and Dealership role access enabled! 