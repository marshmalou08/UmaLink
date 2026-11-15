-- =====================================================================
-- seed.sql  -  Sample records (run after lookup_data.sql)
-- Farmer, Farm, Crop, Planting 11-28 and Harvest 1-20 come from the
-- project file. Planting 1-10 and 29-30 were not visible in the project
-- file, so they follow the same pattern (see README / documentation).
-- =====================================================================

INSERT INTO users (username, full_name, password_hash, role_id) VALUES
 ('admin','System Administrator','$2y$10$1QdRI5tnhhVqONxky5JkHef1esu6nBo4WndyU6CdWBQUUPGcx96gO',1),
 ('staff','Field Staff','$2y$10$KJpSFdVLEyV8qxnbWm3/m.m.up/pYOo6LWxwOwRmkAWoq29Zi5TJW',2);

INSERT INTO farmer (first_name,last_name,contact_number,address,date_registered) VALUES
('Juan','Dela Cruz','09171234567','Cebu City','2026-10-26'),
('Maria','Santos','09181234567','Carcar City, Cebu','2026-10-26'),
('Pedro','Reyes','09191234567','Danao City, Cebu','2026-10-26'),
('Ana','Garcia','09201234567','Argao, Cebu','2026-10-26'),
('Jose','Mendoza','09211234567','Bogo City, Cebu','2026-10-26'),
('Ramon','Flores','09221234567','Toledo City, Cebu','2026-10-26'),
('Elena','Cruz','09231234567','Naga City, Cebu','2026-10-26'),
('Antonio','Lim','09241234567','Minglanilla, Cebu','2026-10-26'),
('Rosa','Fernandez','09251234567','Liloan, Cebu','2026-10-26'),
('Mario','Villanueva','09261234567','Compostela, Cebu','2026-10-26'),
('Teresa','Navarro','09271234567','Talisay City, Cebu','2026-10-26'),
('Carlos','Ramos','09281234567','Consolacion, Cebu','2026-10-26'),
('Liza','Torres','09291234567','Dalaguete, Cebu','2026-10-26'),
('Roberto','Castillo','09301234567','Barili, Cebu','2026-10-26'),
('Susan','Aquino','09311234567','Sibonga, Cebu','2026-10-26');

INSERT INTO farm (farmer_id,farm_name,location,area_hectares,soil_type_id,irrigation_type_id) VALUES
(1,'Dela Cruz Farm','Cebu City',2.50,1,1),
(2,'Santos Rice Farm','Carcar City, Cebu',4.20,2,2),
(3,'Reyes Agricultural Farm','Danao City, Cebu',3.80,3,1),
(4,'Garcia Vegetable Farm','Argao, Cebu',1.75,1,3),
(5,'Mendoza Farm','Bogo City, Cebu',5.00,4,2),
(6,'Flores Corn Farm','Toledo City, Cebu',3.40,3,1),
(7,'Cruz Family Farm','Naga City, Cebu',2.80,1,2),
(8,'Lim Agricultural Farm','Minglanilla, Cebu',2.25,2,3),
(9,'Fernandez Farm','Liloan, Cebu',3.10,3,1),
(10,'Villanueva Green Farm','Compostela, Cebu',4.50,1,2),
(11,'Navarro Rice Field','Talisay City, Cebu',3.75,4,2),
(12,'Ramos Farm','Consolacion, Cebu',2.60,1,1),
(13,'Torres Vegetable Farm','Dalaguete, Cebu',1.80,3,3),
(14,'Castillo Agricultural Farm','Barili, Cebu',5.50,2,2),
(15,'Aquino Farm','Sibonga, Cebu',3.20,1,1);

INSERT INTO crop (crop_name,variety,category_id,growing_period_days,season_id) VALUES
('Rice','NSIC Rc 222',1,115,1),
('Corn','Yellow Corn',1,100,2),
('Tomato','Diamante Max',2,90,2),
('Eggplant','Morena',2,100,3),
('Banana','Lakatan',3,300,3),
('Mango','Carabao',3,150,3),
('Peanut','BPI Pn 9',4,110,3),
('Cassava','Golden Yellow',5,240,3),
('Sweet Potato','NSIC Sp 30',5,120,3),
('Watermelon','Sweet 16',3,85,2);

INSERT INTO planting (farm_id,crop_id,planting_date,area_planted,seed_quantity,expected_harvest_date,status_id) VALUES
(1,1,'2026-01-10',0.50,10.00,'2026-05-05',2),
(2,2,'2026-01-13',0.60,15.00,'2026-04-23',2),
(3,3,'2026-01-16',0.70,20.00,'2026-04-16',2),
(4,4,'2026-01-19',0.80,25.00,'2026-04-29',2),
(5,5,'2026-01-22',0.90,30.00,'2026-11-18',2),
(6,6,'2026-01-25',1.00,35.00,'2026-06-24',2),
(7,7,'2026-01-28',1.10,40.00,'2026-05-18',2),
(8,8,'2026-01-31',1.20,45.00,'2026-09-28',2),
(9,9,'2026-02-03',1.30,10.00,'2026-06-03',2),
(10,10,'2026-02-06',1.40,15.00,'2026-05-02',2),
(11,1,'2026-02-09',0.50,20.00,'2026-06-04',2),
(12,2,'2026-02-12',0.60,25.00,'2026-05-23',2),
(13,3,'2026-02-15',0.70,30.00,'2026-05-16',2),
(14,4,'2026-02-18',0.80,35.00,'2026-05-29',2),
(15,5,'2026-02-21',0.90,40.00,'2026-12-18',2),
(1,6,'2026-02-24',1.00,45.00,'2026-07-24',2),
(2,7,'2026-02-27',1.10,10.00,'2026-06-17',2),
(3,8,'2026-03-02',1.20,15.00,'2026-10-28',2),
(4,9,'2026-03-05',1.30,20.00,'2026-07-03',2),
(5,10,'2026-03-08',1.40,25.00,'2026-06-01',2),
(6,1,'2026-03-11',0.50,30.00,'2026-07-04',1),
(7,2,'2026-03-14',0.60,35.00,'2026-06-22',1),
(8,3,'2026-03-17',0.70,40.00,'2026-06-15',1),
(9,4,'2026-03-20',0.80,45.00,'2026-06-28',1),
(10,5,'2026-03-23',0.90,10.00,'2027-01-17',1),
(11,6,'2026-03-26',1.00,15.00,'2026-08-23',1),
(12,7,'2026-03-29',1.10,20.00,'2026-07-17',1),
(13,8,'2026-04-01',1.20,25.00,'2026-11-27',1),

INSERT INTO harvest (planting_id,harvest_date,quantity_kg,selling_price_per_kg,grade_id,remark_id) VALUES
(1,'2026-05-07',900.00,22.00,1,1),
(2,'2026-04-25',1025.00,25.50,2,1),
(3,'2026-04-18',1150.00,29.00,3,2),
(4,'2026-05-01',1275.00,32.50,1,1),
(5,'2026-11-20',1400.00,36.00,2,1),
(6,'2026-06-26',1525.00,39.50,3,2),
(7,'2026-05-20',1650.00,43.00,1,1),
(8,'2026-09-30',1775.00,46.50,2,1),
(9,'2026-06-05',1900.00,22.00,3,2),
(10,'2026-05-04',2025.00,25.50,1,1),
(11,'2026-06-06',2150.00,29.00,2,1),
(12,'2026-05-25',2275.00,32.50,3,2),
(13,'2026-05-18',2400.00,36.00,1,1),
(14,'2026-05-31',2525.00,39.50,2,1),
(15,'2026-12-20',2650.00,43.00,3,2),
(16,'2026-07-26',2775.00,46.50,1,1),
(17,'2026-06-19',2900.00,22.00,2,1),
(18,'2026-10-30',3025.00,25.50,3,2),
(19,'2026-07-05',3150.00,29.00,1,1),
(20,'2026-06-03',3275.00,32.50,2,1);
