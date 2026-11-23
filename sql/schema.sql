-- ============================================================
-- Anteckningar: tabeller
-- Kör i phpMyAdmin (fliken SQL) eller: mysql -u ANVÄNDARE -p DATABAS < schema.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS Anteckningar (
    ID         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    UserID     INT NOT NULL,
    Namn       VARCHAR(255) NOT NULL DEFAULT '',
    Text       MEDIUMTEXT   NOT NULL,              -- upp till ca 16 MB
    Fast       TINYINT(1)   NOT NULL DEFAULT 0,    -- fäst överst
    Arkiverad  TINYINT(1)   NOT NULL DEFAULT 0,
    Version    INT UNSIGNED NOT NULL DEFAULT 1,    -- skydd mot att två enheter skriver över varandra
    Skapad     DATETIME     NOT NULL,
    Redigerad  DATETIME     NOT NULL,
    PRIMARY KEY (ID),
    KEY idx_lista (UserID, Arkiverad, Fast, Redigerad)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE IF NOT EXISTS AnteckningTaggar (
    ID      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    UserID  INT NOT NULL,
    Namn    VARCHAR(100) NOT NULL,
    PRIMARY KEY (ID),
    UNIQUE KEY uniq_tagg (UserID, Namn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE IF NOT EXISTS AnteckningTaggKoppling (
    AnteckningID INT UNSIGNED NOT NULL,
    TaggID       INT UNSIGNED NOT NULL,
    PRIMARY KEY (AnteckningID, TaggID),
    KEY idx_tagg (TaggID),
    CONSTRAINT fk_koppling_anteckning FOREIGN KEY (AnteckningID) REFERENCES Anteckningar (ID) ON DELETE CASCADE,
    CONSTRAINT fk_koppling_tagg       FOREIGN KEY (TaggID)       REFERENCES AnteckningTaggar (ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;
