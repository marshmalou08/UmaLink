-- =====================================================================
-- lookup_data.sql  -  Reference values (run after schema.sql)
-- The interface shows the description; the database stores the ID.
-- =====================================================================
INSERT INTO role (role_id, role_description) VALUES
 (1,'Administrator'),(2,'Staff');

INSERT INTO soil_type (soil_type_id, soil_type_description) VALUES
 (1,'Loam'),(2,'Clay Loam'),(3,'Sandy Loam'),(4,'Clay');

INSERT INTO irrigation_type (irrigation_type_id, irrigation_type_description) VALUES
 (1,'Rainfed'),(2,'Irrigated'),(3,'Drip Irrigation');

INSERT INTO crop_category (category_id, category_description) VALUES
 (1,'Cereal'),(2,'Vegetable'),(3,'Fruit'),(4,'Legume'),(5,'Root Crop');

INSERT INTO season (season_id, season_description) VALUES
 (1,'Wet Season'),(2,'Dry Season'),(3,'Year Round');

INSERT INTO planting_status (status_id, status_description) VALUES
 (1,'Growing'),(2,'Harvested');

INSERT INTO quality_grade (grade_id, grade_description) VALUES
 (1,'Grade A'),(2,'Grade B'),(3,'Grade C');

INSERT INTO harvest_remark (remark_id, remark_description) VALUES
 (1,'Good Harvest'),
 (2,'Acceptable Quality'),
 (3,'Low Yield'),
 (4,'Poor Quality'),
 (5,'Damaged Harvest');
