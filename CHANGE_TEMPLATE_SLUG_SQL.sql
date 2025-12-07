-- SQL do zmiany slug szablonu ID=5 z 'default-kopia' na 'default'
-- Użycie: Wykonaj w bazie danych pneadm

-- 1. Sprawdź obecny stan
SELECT id, name, slug, is_active, is_default 
FROM certificate_templates 
WHERE id = 5;

-- 2. Sprawdź czy istnieje już szablon z slug 'default'
SELECT id, name, slug 
FROM certificate_templates 
WHERE slug = 'default' AND id != 5;

-- 3. Sprawdź kursy używające szablonu ID=5
SELECT id, title, certificate_template_id 
FROM courses 
WHERE certificate_template_id = 5;

-- 4. Zmień slug (UWAGA: Jeśli istnieje już szablon z slug 'default', 
--    najpierw zmień jego slug na coś innego lub usuń)
UPDATE certificate_templates 
SET slug = 'default' 
WHERE id = 5;

-- 5. Weryfikacja
SELECT id, name, slug, is_active, is_default 
FROM certificate_templates 
WHERE id = 5;




