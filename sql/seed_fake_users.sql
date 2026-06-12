-- Script de génération de 50 utilisateurs fictifs pour ChicBook
-- Exécuter avec: docker exec chicbook_db psql -U chicuser -d chicbook -f /path/to/seed_fake_users.sql

-- Noms français réalistes pour le seed
WITH fake_data AS (
    SELECT * FROM (
        VALUES
            -- (prenom, nom, profession, ville, gender)
            ('Marc', 'Dubois', 'Styliste', 'Paris', 'Homme'),
            ('Sophie', 'Martin', 'Mannequin', 'Paris', 'Femme'),
            ('Julien', 'Bernard', 'Photographe', 'Lyon', 'Homme'),
            ('Isabelle', 'Thomas', 'Maquilleur', 'Marseille', 'Femme'),
            ('Nicolas', 'Robert', 'Vidéaste', 'Paris', 'Homme'),
            ('Céline', 'Richard', 'Danseur', 'Nice', 'Femme'),
            ('Xavier', 'Petit', 'Designer textile', 'Paris', 'Homme'),
            ('Marine', 'Durand', 'Mannequin', 'Bordeaux', 'Femme'),
            ('Adrien', 'Lefevre', 'Coiffeur', 'Paris', 'Homme'),
            ('Aurore', 'Moreau', 'Styliste', 'Lyon', 'Femme'),
            ('Benoit', 'Simon', 'Photographe', 'Paris', 'Homme'),
            ('Claire', 'Michel', 'Modéliste', 'Paris', 'Femme'),
            ('David', 'Garcia', 'Videaste', 'Toulouse', 'Homme'),
            ('Emma', 'Martinez', 'Mannequin', 'Paris', 'Femme'),
            ('Fabrice', 'Lopez', 'Comédien', 'Paris', 'Homme'),
            ('Gabrielle', 'Gonzalez', 'Danseur', 'Paris', 'Femme'),
            ('Henri', 'Rodriguez', 'Photographe', 'Nantes', 'Homme'),
            ('Henriette', 'Sanchez', 'Maquilleur', 'Paris', 'Femme'),
            ('Ignace', 'Perez', 'Designer accessoires', 'Paris', 'Homme'),
            ('Iris', 'Torres', 'Styliste', 'Aix-en-Provence', 'Femme'),
            ('Jacques', 'Rivera', 'Coiffeur', 'Paris', 'Homme'),
            ('Jacqueline', 'Peterson', 'Mannequin', 'Paris', 'Femme'),
            ('Kevin', 'Young', 'Videaste', 'Paris', 'Homme'),
            ('Katia', 'King', 'Photographe', 'Strasbourg', 'Femme'),
            ('Laurent', 'Wright', 'Comédien', 'Paris', 'Homme'),
            ('Laurence', 'Lopez', 'Danseur', 'Paris', 'Femme'),
            ('Marc', 'Hill', 'Mannequin', 'Paris', 'Homme'),
            ('Margot', 'Scott', 'Styliste', 'Paris', 'Femme'),
            ('Mathieu', 'Green', 'Maquilleur', 'Paris', 'Homme'),
            ('Mathilde', 'Adams', 'Photographe', 'Lille', 'Femme'),
            ('Olivier', 'Nelson', 'Brodeur / Ornementation', 'Paris', 'Homme'),
            ('Odette', 'Carter', 'Modéliste', 'Paris', 'Femme'),
            ('Pascal', 'Mitchell', 'Videaste', 'Nantes', 'Homme'),
            ('Pascale', 'Perez', 'Mannequin', 'Paris', 'Femme'),
            ('Quentin', 'Roberts', 'Photographe', 'Paris', 'Homme'),
            ('Quitterie', 'Phillips', 'Coiffeur', 'Paris', 'Femme'),
            ('Raphael', 'Campbell', 'Comédien', 'Paris', 'Homme'),
            ('Rosalie', 'Parker', 'Danseur', 'Paris', 'Femme'),
            ('Sebastien', 'Evans', 'Designer textile', 'Paris', 'Homme'),
            ('Stephanie', 'Edwards', 'Styliste', 'Cannes', 'Femme'),
            ('Thierry', 'Collins', 'Mannequin', 'Paris', 'Homme'),
            ('Therese', 'Stewart', 'Maquilleur', 'Paris', 'Femme'),
            ('Urbain', 'Sanchez', 'Photographe', 'Marseille', 'Homme'),
            ('Ursula', 'Morris', 'Videaste', 'Paris', 'Femme'),
            ('Vincent', 'Rogers', 'Comédien', 'Paris', 'Homme'),
            ('Vanessa', 'Morgan', 'Mannequin', 'Paris', 'Femme'),
            ('Yves', 'Peterson', 'Designer accessoires', 'Paris', 'Homme'),
            ('Yvette', 'Gibson', 'Danseur', 'Paris', 'Femme'),
            ('Zacharie', 'Berry', 'Photographe', 'Paris', 'Homme'),
            ('Zoé', 'Cox', 'Styliste', 'Paris', 'Femme')
    ) AS t(prenom, nom, profession, ville, gender)
)
-- Insertion des utilisateurs
INSERT INTO users (
    role, gender, full_name, first_name, email, password_hash, 
    city, country, specific_profession, expertise_tags, bio, birth_date, show_age
)
SELECT
    'user' AS role,
    gender,
    prenom || ' ' || nom AS full_name,
    prenom AS first_name,
    LOWER(prenom || '.' || nom || '@chicbook.test') AS email,
    '$2y$10$eIxZaYVK3QqZgMwvEPNH2.Qxd3T7Fqjn6V8Fz7xPnCYkQ3PHKD7Hm' AS password_hash, -- "password123" hash
    ville AS city,
    'France' AS country,
    profession AS specific_profession,
    CASE 
        WHEN profession = 'Styliste' THEN 'mode,design,tendances,création'
        WHEN profession = 'Mannequin' THEN 'mode,photographie,runway,shooting'
        WHEN profession = 'Photographe' THEN 'photographie,portrait,fashion,studio'
        WHEN profession = 'Maquilleur' THEN 'beauté,makeup,cosmétiques,retouche'
        WHEN profession = 'Videaste' THEN 'vidéo,montage,production,cinema'
        WHEN profession = 'Danseur' THEN 'danse,chorégraphie,mouvement,spectacle'
        WHEN profession = 'Coiffeur' THEN 'coiffure,coupe,coloration,style'
        WHEN profession = 'Modéliste' THEN 'couture,modèles,pattern,textile'
        WHEN profession = 'Designer accessoires' THEN 'accessoires,design,bijoux,création'
        WHEN profession = 'Designer textile' THEN 'textile,motifs,tissus,création'
        WHEN profession = 'Brodeur / Ornementation' THEN 'broderie,ornementation,détails,artisanat'
        WHEN profession = 'Comédien' THEN 'cinéma,théâtre,jeu,casting'
        ELSE 'professionnel,créatif'
    END AS expertise_tags,
    'Professionnel(le) du secteur mode et image. Passionné par la création et l''esthétique.' AS bio,
    (CURRENT_DATE - (INTERVAL '1 day' * (FLOOR(RANDOM() * 15000 + 7300))))::DATE AS birth_date,
    TRUE AS show_age
FROM fake_data;

-- Insertion des associations user_professions (linkedées à la profession spécifique)
INSERT INTO user_professions (user_id, profession_id)
SELECT 
    u.id,
    p.id
FROM users u
JOIN professions p ON u.specific_profession = p.name
WHERE u.email LIKE '%.test'
ORDER BY u.id;

-- Insertion des mensurations pour mannequins, danseurs et comédiens
INSERT INTO measurements (user_id, birth_date, height, chest_size, waist_size, hip_size, shoe_size, eye_color_id, hair_color_id, ethnicity_id)
SELECT
    u.id,
    u.birth_date,
    (FLOOR(RANDOM() * 15 + 163))::INT AS height, -- 163-178 cm
    CASE FLOOR(RANDOM() * 3)
        WHEN 0 THEN 'XS'
        WHEN 1 THEN 'S'
        WHEN 2 THEN 'M'
        ELSE 'L'
    END AS chest_size,
    CASE FLOOR(RANDOM() * 3)
        WHEN 0 THEN '36'
        WHEN 1 THEN '38'
        WHEN 2 THEN '40'
        ELSE '42'
    END AS waist_size,
    CASE FLOOR(RANDOM() * 3)
        WHEN 0 THEN '36'
        WHEN 1 THEN '38'
        WHEN 2 THEN '40'
        ELSE '42'
    END AS hip_size,
    CASE FLOOR(RANDOM() * 3)
        WHEN 0 THEN '37'
        WHEN 1 THEN '38'
        ELSE '39'
    END AS shoe_size,
    (FLOOR(RANDOM() * 8) + 1)::INT AS eye_color_id, -- 1 à 8
    (FLOOR(RANDOM() * 9) + 1)::INT AS hair_color_id, -- 1 à 9
    (FLOOR(RANDOM() * 8) + 1)::INT AS ethnicity_id -- 1 à 8
FROM users u
WHERE u.email LIKE '%.test'
  AND u.specific_profession IN ('Mannequin', 'Danseur', 'Comédien')
ON CONFLICT DO NOTHING;

-- Affichage du résumé
SELECT 
    'Utilisateurs créés' AS action,
    COUNT(*) AS nombre
FROM users
WHERE email LIKE '%.test'
UNION ALL
SELECT 
    'Associations professions',
    COUNT(*)
FROM user_professions up
JOIN users u ON up.user_id = u.id
WHERE u.email LIKE '%.test'
UNION ALL
SELECT 
    'Mensurations ajoutées',
    COUNT(*)
FROM measurements m
JOIN users u ON m.user_id = u.id
WHERE u.email LIKE '%.test';
