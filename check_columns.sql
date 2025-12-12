SELECT COLUMN_NAME 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'instructors' 
  AND COLUMN_NAME IN ('website_url', 'linkedin_url', 'facebook_url', 'youtube_url', 'x_com_url')
ORDER BY COLUMN_NAME;
