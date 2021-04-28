# Database changes

    ALTER TABLE feedinc ADD lookbackPeriod TINYINT UNSIGNED DEFAULT 120 NOT NULL;

# TODO

- Ability to select columns for outbound export