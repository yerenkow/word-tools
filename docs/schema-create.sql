begin;
CREATE TABLE wt_indexed_string
(
		`id` integer NOT NULL AUTO_INCREMENT ,
		`content` character varying(250) ,
		`length` integer ,

		CONSTRAINT pk_wt_indexed_string PRIMARY KEY (`id`)
);
CREATE TABLE wt_text_block
(
		`id` integer NOT NULL AUTO_INCREMENT ,
    `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
		`name_id` integer ,
		`parent_id` integer ,
		`child_order` integer ,

		CONSTRAINT pk_wt_text_block PRIMARY KEY (`id`)
);
CREATE TABLE wt_text_chunk
(
		`id` integer NOT NULL AUTO_INCREMENT ,
		`text_block_id` integer ,
		`order_number` integer ,
		`content_id` integer ,
		`leftover_content_id` integer ,

		CONSTRAINT pk_wt_text_chunk PRIMARY KEY (`id`)
);
CREATE TABLE wt_chunk_in_block_frequency_record
(
		`id` integer NOT NULL AUTO_INCREMENT ,
		`content_id1` integer ,
		`content_id2` integer ,
		`text_block_id` integer ,
		`frequency` double precision ,

		CONSTRAINT pk_wt_chunk_in_block_frequency_record PRIMARY KEY (`id`)
);
CREATE TABLE wt_ignored_word
(
		`id` integer NOT NULL AUTO_INCREMENT ,
		`content_id` integer ,
		`text_block_id` integer ,

		CONSTRAINT pk_wt_ignored_word PRIMARY KEY (`id`)
);

ALTER TABLE `wt_indexed_string` ADD INDEX `content` (`content`);
ALTER TABLE `wt_text_chunk` ADD INDEX `text_block_id` (`text_block_id`);
ALTER TABLE `wt_text_chunk` ADD INDEX `order_number` (`order_number`);
ALTER TABLE `wt_text_chunk` ADD INDEX `content_id` (`content_id`);
ALTER TABLE `wt_text_chunk` ADD INDEX `leftover_content_id` (`leftover_content_id`);

ALTER TABLE `wt_chunk_in_block_frequency_record` ADD INDEX `content_id1` (`content_id1`);
ALTER TABLE `wt_chunk_in_block_frequency_record` ADD INDEX `content_id2` (`content_id2`);
ALTER TABLE `wt_chunk_in_block_frequency_record` ADD INDEX `text_block_id` (`text_block_id`);

rollback;