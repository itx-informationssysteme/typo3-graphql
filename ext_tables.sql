CREATE TABLE tx_typo3graphql_domain_model_filter
(
	name        varchar(255) DEFAULT '' NOT NULL,
	model       varchar(255) DEFAULT '' NOT NULL,
	filter_path varchar(255) DEFAULT '' NOT NULL,
	categories  int(11) DEFAULT '0' NOT NULL,
	unit        varchar(255) DEFAULT '' NOT NULL
);

CREATE INDEX idx_filterpath ON tx_typo3graphql_domain_model_filter (filter_path);
