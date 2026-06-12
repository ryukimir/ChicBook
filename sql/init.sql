CREATE TABLE
    professions (id SERIAL PRIMARY KEY, name VARCHAR(100) NOT NULL);

CREATE TABLE
    languages (id SERIAL PRIMARY KEY, name VARCHAR(100) NOT NULL);

CREATE TABLE
    eye_colors (id SERIAL PRIMARY KEY, name VARCHAR(50) NOT NULL);

CREATE TABLE
    hair_colors (id SERIAL PRIMARY KEY, name VARCHAR(50) NOT NULL);

CREATE TABLE
    ethnicities (id SERIAL PRIMARY KEY, name VARCHAR(100) NOT NULL);

CREATE TABLE
    users (
        id SERIAL PRIMARY KEY,
        role VARCHAR(50) NOT NULL,
        gender VARCHAR(50),
        full_name VARCHAR(255) NOT NULL,
        first_name VARCHAR(50),
        email VARCHAR(255) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        city VARCHAR(100),
        country VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        login_code VARCHAR(10),
        profile_picture_url VARCHAR(255),
        specific_profession VARCHAR(255),
        expertise_tags TEXT,
        bio TEXT
    );

CREATE TABLE
    user_professions (
        user_id INT,
        profession_id INT,
        PRIMARY KEY (user_id, profession_id),
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
        FOREIGN KEY (profession_id) REFERENCES professions (id) ON DELETE CASCADE
    );

CREATE TABLE
    user_languages (
        user_id INT,
        language_id INT,
        PRIMARY KEY (user_id, language_id),
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
        FOREIGN KEY (language_id) REFERENCES languages (id) ON DELETE CASCADE
    );

CREATE TABLE
    followers (
        follower_id INT,
        followed_id INT,
        PRIMARY KEY (follower_id, followed_id),
        FOREIGN KEY (follower_id) REFERENCES users (id) ON DELETE CASCADE,
        FOREIGN KEY (followed_id) REFERENCES users (id) ON DELETE CASCADE
    );

CREATE TABLE
    measurements (
        id SERIAL PRIMARY KEY,
        user_id INT UNIQUE NOT NULL,
        birth_date DATE,
        height INT,
        chest_size VARCHAR(20),
        cup_size VARCHAR(10),
        waist_size VARCHAR(20),
        hip_size VARCHAR(20),
        shoe_size VARCHAR(10),
        eye_color_id INT,
        hair_color_id INT,
        ethnicity_id INT,
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
        FOREIGN KEY (eye_color_id) REFERENCES eye_colors (id) ON DELETE SET NULL,
        FOREIGN KEY (hair_color_id) REFERENCES hair_colors (id) ON DELETE SET NULL,
        FOREIGN KEY (ethnicity_id) REFERENCES ethnicities (id) ON DELETE SET NULL
    );

CREATE TABLE
    portfolios (
        id SERIAL PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255),
        image_url VARCHAR(255) NOT NULL,
        description TEXT,
        tags TEXT,
        position INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
    );

CREATE TABLE
    portfolio_likes (
        user_id INT,
        portfolio_id INT,
        PRIMARY KEY (user_id, portfolio_id),
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
        FOREIGN KEY (portfolio_id) REFERENCES portfolios (id) ON DELETE CASCADE
    );

CREATE TABLE
    castings (
        id SERIAL PRIMARY KEY,
        user_id INT NOT NULL,
        description TEXT,
        company_name VARCHAR(255),
        country VARCHAR(100),
        city VARCHAR(100),
        role_sought VARCHAR(100),
        cover_image VARCHAR(255),
        duration VARCHAR(50),
        performance_date DATE,
        casting_date DATE,
        location VARCHAR(255),
        collaboration_type VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
    );

CREATE TABLE
    projects (
        id SERIAL PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        expected_date DATE,
        contact_name VARCHAR(255),
        contact_email VARCHAR(255),
        contact_phone VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
    );

CREATE TABLE
    project_likes (
        user_id INT,
        project_id INT,
        PRIMARY KEY (user_id, project_id),
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
        FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
    );

CREATE TABLE
    required_profiles (
        id SERIAL PRIMARY KEY,
        project_id INT NOT NULL,
        gender VARCHAR(50),
        quantity INT DEFAULT 1,
        min_height INT,
        max_height INT,
        min_age INT,
        max_age INT,
        chest_size VARCHAR(20),
        cup_size VARCHAR(10),
        waist_size VARCHAR(20),
        hip_size VARCHAR(20),
        shoe_size VARCHAR(10),
        eye_color_id INT,
        hair_color_id INT,
        ethnicity_id INT,
        FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
        FOREIGN KEY (eye_color_id) REFERENCES eye_colors (id) ON DELETE SET NULL,
        FOREIGN KEY (hair_color_id) REFERENCES hair_colors (id) ON DELETE SET NULL,
        FOREIGN KEY (ethnicity_id) REFERENCES ethnicities (id) ON DELETE SET NULL
    );

CREATE TABLE
    casting_profiles (
        id SERIAL PRIMARY KEY,
        casting_id INT NOT NULL,
        role_name VARCHAR(100) NOT NULL,
        quantity INT DEFAULT 1,
        age_range VARCHAR(50),
        gender VARCHAR(50),
        eye_color_id INT,
        hair_color_id INT,
        ethnicity_id INT,
        height VARCHAR(50),
        shoe_size VARCHAR(50),
        waist_size VARCHAR(50),
        hip_size VARCHAR(50),
        chest_size VARCHAR(50),
        cup_size VARCHAR(10),
        FOREIGN KEY (casting_id) REFERENCES castings (id) ON DELETE CASCADE,
        FOREIGN KEY (eye_color_id) REFERENCES eye_colors (id) ON DELETE SET NULL,
        FOREIGN KEY (hair_color_id) REFERENCES hair_colors (id) ON DELETE SET NULL,
        FOREIGN KEY (ethnicity_id) REFERENCES ethnicities (id) ON DELETE SET NULL
    );

INSERT INTO
    professions (name)
VALUES
    ('Styliste'),
    ('Modéliste'),
    ('Designer accessoires'),
    ('Designer textile'),
    ('Brodeur / Ornementation'),
    ('Photographe'),
    ('Vidéaste'),
    ('Mannequin'),
    ('Comédien'),
    ('Danseur'),
    ('Coiffeur'),
    ('Maquilleur');

INSERT INTO
    eye_colors (name)
VALUES
    ('Bleu'),
    ('Vert'),
    ('Marron'),
    ('Gris'),
    ('Noisette'),
    ('Noir'),
    ('Ambre'),
    ('Vairon');

INSERT INTO
    hair_colors (name)
VALUES
    ('Blond'),
    ('Brun'),
    ('Châtain'),
    ('Roux'),
    ('Noir'),
    ('Gris'),
    ('Blanc'),
    ('Chauve'),
    ('Couleur fantaisie');

INSERT INTO
    ethnicities (name)
VALUES
    ('Caucasien'),
    ('Africain / Noir'),
    ('Asiatique'),
    ('Latino / Hispanique'),
    ('Moyen-Oriental'),
    ('Indien'),
    ('Métis'),
    ('Autochtone');

INSERT INTO
    languages (name)
VALUES
    ('Français'),
    ('Anglais'),
    ('Espagnol'),
    ('Italien'),
    ('Allemand'),
    ('Arabe'),
    ('Mandarin');

ALTER TABLE projects
ADD COLUMN project_type VARCHAR(100),
ADD COLUMN searched_profiles VARCHAR(255);

ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_theme VARCHAR(50) DEFAULT 'classique';

ALTER TABLE users
ADD COLUMN IF NOT EXISTS birth_date DATE;

CREATE TABLE
    project_moodboards (
        id SERIAL PRIMARY KEY,
        project_id INT NOT NULL,
        image_url VARCHAR(255) NOT NULL,
        FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
    );

CREATE TABLE
    project_team (
        id SERIAL PRIMARY KEY,
        project_id INT NOT NULL,
        role VARCHAR(100),
        linked_user_id INT,
        manual_name VARCHAR(100),
        manual_measurements VARCHAR(255),
        FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
        FOREIGN KEY (linked_user_id) REFERENCES users (id) ON DELETE SET NULL
    );

CREATE TABLE IF NOT EXISTS casting_favorites (
    user_id INT,
    casting_id INT,
    PRIMARY KEY (user_id, casting_id),
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (casting_id) REFERENCES castings (id) ON DELETE CASCADE
);

-- Back office
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_admin BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_suspended BOOLEAN DEFAULT FALSE;

-- Événements
CREATE TABLE IF NOT EXISTS events (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    type VARCHAR(100),
    organizer VARCHAR(255),
    city VARCHAR(100),
    country VARCHAR(100),
    event_date DATE,
    cover_image VARCHAR(255),
    description TEXT,
    price VARCHAR(100),
    capacity INT,
    tags TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS event_registrations (
    user_id INT,
    event_id INT,
    PRIMARY KEY (user_id, event_id),
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE
);
ALTER TABLE users ADD COLUMN IF NOT EXISTS show_age BOOLEAN DEFAULT FALSE;

ALTER TABLE users ADD COLUMN IF NOT EXISTS gender VARCHAR(50);

-- Messagerie
CREATE TABLE IF NOT EXISTS conversations (
    id SERIAL PRIMARY KEY,
    user1_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    user2_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(user1_id, user2_id)
);

CREATE TABLE IF NOT EXISTS messages (
    id SERIAL PRIMARY KEY,
    conversation_id INT NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
    sender_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT NOW(),
    is_read BOOLEAN DEFAULT FALSE
);


-- Signalements
CREATE TABLE IF NOT EXISTS reports (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE SET NULL,
    category VARCHAR(50) NOT NULL DEFAULT 'autre',
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Connexion persistante (remember me)
ALTER TABLE users ADD COLUMN IF NOT EXISTS remember_token VARCHAR(64);

-- Projets : nom du rôle sur les profils requis
ALTER TABLE required_profiles ADD COLUMN IF NOT EXISTS role_name VARCHAR(100);

-- Suggestions utilisateurs
CREATE TABLE IF NOT EXISTS suggestions (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE SET NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Catégories de métiers (back office)
CREATE TABLE IF NOT EXISTS profession_categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    display_order INT DEFAULT 0
);

ALTER TABLE professions ADD COLUMN IF NOT EXISTS category_id INT REFERENCES profession_categories(id) ON DELETE SET NULL;
ALTER TABLE professions ADD COLUMN IF NOT EXISTS has_measurements BOOLEAN DEFAULT FALSE;

-- Données initiales catégories
INSERT INTO profession_categories (name, display_order)
SELECT * FROM (VALUES ('Création & Design', 1), ('Image & Production', 2), ('Marques & Créateurs', 3)) AS v(name, display_order)
WHERE NOT EXISTS (SELECT 1 FROM profession_categories LIMIT 1);

-- Professions avec mensurations
UPDATE professions SET has_measurements = TRUE WHERE name IN ('Mannequin', 'Comédien', 'Danseur');

-- Assignation des catégories aux professions existantes
UPDATE professions SET category_id = (SELECT id FROM profession_categories WHERE name = 'Création & Design')
WHERE name IN ('Styliste', 'Modéliste', 'Designer accessoires', 'Designer textile', 'Brodeur / Ornementation') AND category_id IS NULL;

UPDATE professions SET category_id = (SELECT id FROM profession_categories WHERE name = 'Image & Production')
WHERE name IN ('Mannequin', 'Photographe', 'Vidéaste', 'Maquilleur', 'Coiffeur', 'Comédien', 'Danseur') AND category_id IS NULL;


-- Table follows (système de suivi)
CREATE TABLE IF NOT EXISTS follows (
    id SERIAL PRIMARY KEY,
    follower_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    following_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(follower_id, following_id)
);


-- Migration: colonnes reset mot de passe
ALTER TABLE users ADD COLUMN IF NOT EXISTS password_reset_token VARCHAR(64);
ALTER TABLE users ADD COLUMN IF NOT EXISTS password_reset_expires TIMESTAMP;
-- Table tags expertise (gérés depuis le back office)
CREATE TABLE IF NOT EXISTS expertise_tags_list (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Seed tags par défaut
INSERT INTO expertise_tags_list (name, display_order) VALUES
('Haute couture',1),('Luxe',2),('Editorial',3),('Créatif',4),('Premium',5),
('Fashion week',6),('Minimaliste',7),('Streetwear',8),('Avant-garde',9),('Moderne',10),
('International',11),('Haut de gamme',12),('Commercial',13),('Artistique',14),
('Perlage',15),('Ornementation',16),('Textile',17),('Broderie',18),('Brodeur',19),
('Couture',20),('Défilé',21),('Beauté',22),('Hair stylist',23),('Mode',24),
('Acteur',25),('Campagne',26),('Publicité',27),('Fashion',28),('Film',29),
('Contemporain',30),('Performance',31),('Mouvement',32),('Designer',33),
('Sacs',34),('Bijoux',35),('Chaussures',36),('Maroquinerie',37),('Accessoires',38),
('Imprimés',39),('Maille',40),('Surface',41),('Mannequin',42),('Maquilleur',43),
('Modéliste',44),('Patronage',45),('Atelier',46),('Photographe',47),('Studio',48),
('Styliste',49),('Créateur',50),('Photo',51),('Célébrité',52),('Plateau',53),
('Vidéaste',54),('Backstage',55),('Réalisateur',56),('Contenu',57),
('Coiffeur',58),('Comédien',59),('Danseur',60)
ON CONFLICT (name) DO NOTHING;
