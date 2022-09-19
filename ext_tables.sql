CREATE TABLE tx_typo3_graphql_domain_model_filter
(
	name        varchar(255) DEFAULT ''  NOT NULL,
	model       varchar(255) DEFAULT ''  NOT NULL,
	filter_path varchar(255) DEFAULT ''  NOT NULL,
	categories  int(11)      DEFAULT '0' NOT NULL
);
