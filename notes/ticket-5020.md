# Database changes

    XXXINSERT INTO configuration(config_key,config_value) VALUES('mojo_media_api_key', 'XXX');
    XXXALTER TABLE feedinc ADD filterMojoMedia TINYINT UNSIGNED DEFAULT 0 NOT NULL;

    We removed this setting now.
    ALTER TABLE feedinc DROP COLUMN filterMojoMedia;

# TODO

