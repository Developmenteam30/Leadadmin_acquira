# Database changes

    INSERT INTO configuration(config_key,config_value) VALUES('mojo_media_api_key', 'XXX');
    ALTER TABLE feedinc ADD filterMojoMedia TINYINT UNSIGNED DEFAULT 0 NOT NULL;

# TODO

