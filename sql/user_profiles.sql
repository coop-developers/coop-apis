/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_profiles` (
  `user_id` int(11) NOT NULL,
  `suite` varchar(50) COLLATE utf8_bin NOT NULL,
  `lease_period` varchar(255) COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;
