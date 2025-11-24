<?php
/**
 * SMSA Database Model Class
 *
 * Handles database operations for SMSA shipments
 *
 * @package SMSA_WooCommerce
 * @subpackage Includes
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SMSA_Model
 *
 * Database model for SMSA shipment data
 */
class SMSA_Model {

    /**
     * Get consignment records for an order
     *
     * @param int $order_id The WooCommerce order ID
     * @return array Database results
     */
    public static function get_consignment($order_id) {
        global $table_prefix, $wpdb;

        $query = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_prefix}smsa WHERE order_id = %d ORDER BY date_added DESC",
                (int) $order_id
            )
        );

        return $query;
    }

    /**
     * Save shipment record to database
     *
     * @param array $data Shipment data to save
     * @return int|false The number of rows inserted, or false on error
     */
    public static function save_shipment($data) {
        global $table_prefix, $wpdb;

        $table_name = $table_prefix . 'smsa';

        return $wpdb->insert($table_name, $data);
    }

    /**
     * Get city ID by name
     *
     * @param string $city_name City name to search
     * @param int $language_id Language ID (1=English, 2=Arabic)
     * @return int|null City ID or null if not found
     */
    public static function get_city_id($city_name, $language_id = 1) {
        global $table_prefix, $wpdb;

        $query = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT city_id FROM {$table_prefix}smsa_city WHERE name LIKE %s AND language_id = %d LIMIT 1",
                '%' . $wpdb->esc_like($city_name) . '%',
                (int) $language_id
            )
        );

        return $query ? $query->city_id : null;
    }

    /**
     * Get city name by ID
     *
     * @param int $city_id City ID
     * @param int $language_id Language ID (1=English, 2=Arabic)
     * @return string|null City name or null if not found
     */
    public static function get_city_name($city_id, $language_id = 2) {
        global $table_prefix, $wpdb;

        $query = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT name FROM {$table_prefix}smsa_city WHERE city_id = %d AND language_id = %d LIMIT 1",
                (int) $city_id,
                (int) $language_id
            )
        );

        return $query ? $query->name : null;
    }

    /**
     * Get Arabic city name from English name
     *
     * @param string $english_name English city name
     * @return string Arabic city name or original if not found
     */
    public static function get_arabic_city_name($english_name) {
        $city_id = self::get_city_id($english_name, 1);
        if ($city_id) {
            $arabic_name = self::get_city_name($city_id, 2);
            if ($arabic_name) {
                return $arabic_name;
            }
        }
        return $english_name;
    }

    /**
     * Create plugin database tables
     *
     * @return void
     */
    public static function create_tables() {
        global $table_prefix, $wpdb;

        $wp_smsa_table = $table_prefix . 'smsa';
        $wp_smsa_city_table = $table_prefix . 'smsa_city';

        require_once(ABSPATH . '/wp-admin/includes/upgrade.php');

        // Create shipments table
        if ($wpdb->get_var("SHOW TABLES LIKE '$wp_smsa_table'") != $wp_smsa_table) {
            $sql = "CREATE TABLE `{$wp_smsa_table}` (
                `consignment_id` int(11) NOT NULL AUTO_INCREMENT,
                `order_id` int(11) NOT NULL,
                `awb_number` varchar(32) NOT NULL,
                `reference_number` varchar(32) NOT NULL,
                `pickup_date` datetime NOT NULL,
                `shipment_label` text,
                `status` varchar(32) NOT NULL,
                `date_added` datetime NOT NULL,
                PRIMARY KEY (`consignment_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;";

            dbDelta($sql);
        }

        // Create cities table
        if ($wpdb->get_var("SHOW TABLES LIKE '$wp_smsa_city_table'") != $wp_smsa_city_table) {
            $sql = "CREATE TABLE `{$wp_smsa_city_table}` (
                `city_id` int(11) NOT NULL,
                `language_id` int(11) NOT NULL,
                `name` varchar(32) NOT NULL
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;";

            dbDelta($sql);

            // Insert city data
            self::insert_city_data();
        }

        // Create AWB directory
        $awb_dir = ABSPATH . 'awb/';
        if (!is_dir($awb_dir)) {
            mkdir($awb_dir, 0755, true);
        }
    }

    /**
     * Insert city data into database
     *
     * @return void
     */
    private static function insert_city_data() {
        global $table_prefix, $wpdb;

        $wp_smsa_city_table = $table_prefix . 'smsa_city';

        $wpdb->query("INSERT INTO `{$wp_smsa_city_table}` (`city_id`, `language_id`, `name`) VALUES (3, 1, 'Aqiq'),(3, 2, 'العقيق'),(4, 1, 'Atawlah'),(4, 2, 'الأطاولة '),(5, 1, 'Baha'),(5, 2, 'الباحة'),(6, 1, 'Baljurashi'),(6, 2, 'بلجرشي'),(7, 1, 'Mandaq'),(7, 2, 'المندق'),(8, 2, 'المظيلف'),(8, 1, 'Mudhaylif'),(9, 1, 'Mukhwah'),(9, 2, 'المخواة'),(10, 1, 'Qilwah'),(10, 2, 'قلوة'),(11, 1, 'Qunfudhah'),(11, 2, 'القنفذة'),(12, 2, 'الجوف'),(12, 1, 'Al Jouf'),(13, 1, 'Dawmat Al Jandal'),(13, 2, 'دومة الجندل'),(14, 1, 'Skakah'),(14, 2, 'سكاكا'),(15, 1, 'Bashayer'),(15, 2, 'البشائر'),(16, 1, 'Bellasmar'),(16, 2, 'بللسمر'),(17, 1, 'Namas'),(17, 2, 'النماص'),(18, 1, 'Sapt Al Alaya'),(18, 2, 'سبت العلايا'),(19, 1, 'Tanumah'),(19, 2, 'تنومة'),(20, 1, 'Ain Dar'),(20, 2, 'عين دار'),(21, 1, 'Anak'),(21, 2, 'عنك'),(22, 1, 'Buqaiq'),(22, 2, 'بقيق'),(23, 1, 'Dammam'),(23, 2, 'الدمام'),(24, 1, 'Dammam Airport'),(24, 2, 'مطار الدمام'),(25, 1, 'Dhahran'),(25, 2, 'الظهران'),(26, 1, 'Jubail'),(26, 2, 'الجبيل'),(27, 1, 'Khafji'),(27, 2, 'الخفجي'),(28, 1, 'Khubar'),(28, 2, 'الخبر'),(29, 1, 'Nairiyah'),(29, 2, 'النعيرية'),(30, 1, 'Qarya Al Uliya'),(30, 2, 'قرية العليا'),(31, 1, 'Qatif'),(31, 2, 'القطيف'),(32, 1, 'Rahima'),(32, 2, 'رحيمة'),(33, 1, 'Ras Tannurah'),(33, 2, 'رأس تنورة'),(34, 1, 'Safwa'),(34, 2, 'صفوى'),(35, 1, 'Saira'),(35, 2, 'سايرة'),(36, 1, 'Sayhat'),(36, 2, 'سيهات'),(37, 1, 'Shedgum'),(37, 2, 'شدقم'),(38, 1, 'Tanajib'),(38, 2, 'تناجيب'),(39, 2, 'تاروت (دارين)'),(39, 1, 'Tarut (Darin)'),(40, 1, 'Thqbah'),(40, 2, 'الثقبة'),(41, 1, 'Udhayliyah'),(41, 2, 'العضيلية '),(42, 1, 'Uthmaniyah'),(42, 2, 'العثمانية'),(43, 1, 'Najran'),(43, 2, 'نجران '),(44, 1, 'Sharourah'),(44, 2, 'شرورة'),(45, 1, 'Wadi Al-Dawasir'),(45, 2, 'وادي الدواسر'),(46, 1, 'Badaya '),(46, 2, 'البدائع'),(47, 1, 'Bukayriyah'),(47, 2, 'البكيرية '),(48, 1, 'Buraydah'),(48, 2, 'بريدة'),(49, 1, 'Dukhnah'),(49, 2, 'دخنة'),(50, 1, 'Khabra'),(50, 2, 'الخبراء'),(51, 1, 'Midhnab'),(51, 2, 'المذنب'),(52, 1, 'Nabhaniah'),(52, 2, 'النبهانية '),(53, 1, 'Qaseem Airport'),(53, 2, 'مطار القصيم '),(54, 1, 'Rafayaa Al Gimsh'),(54, 2, 'رفايع الجمش '),(55, 1, 'Rass'),(55, 2, 'الرس'),(56, 1, 'Riyadh Al Khabra'),(56, 2, 'رياض الخبراء '),(57, 1, 'Sajir'),(57, 2, 'ساجر'),(58, 1, 'Unayzah'),(58, 2, 'عنيزة'),(59, 1, 'Uqlat As Suqur'),(59, 2, 'عقلة الصقر'),(60, 1, 'Jeddah'),(60, 2, 'جدة'),(61, 1, 'Uyun Al Jiwa'),(61, 2, 'عيون الجواء '),(62, 1, 'Abu Arish'),(62, 2, 'أبو عريش'),(63, 1, 'Ahad Al Masarhah'),(63, 2, 'أحد المسارحة'),(64, 1, 'Al Dayer'),(64, 2, 'الدائر '),(65, 1, 'At Tuwal'),(65, 2, 'طوال'),(66, 1, 'Bani Malek '),(66, 2, 'بني مالك'),(67, 1, 'Baysh'),(67, 2, 'بيش'),(68, 1, 'Darb'),(68, 2, 'درب'),(69, 1, 'Dhamad'),(69, 2, 'ضمد'),(70, 1, 'Farasan'),(70, 2, 'فرسان'),(71, 1, 'Jazan'),(71, 2, 'جازان'),(72, 1, 'Sabya'),(72, 2, 'صبيا'),(73, 1, 'Samtah'),(73, 2, 'صامطة'),(74, 1, 'Shuqayq'),(74, 2, 'الشقيق'),(75, 2, 'حائل'),(75, 1, 'Hail'),(76, 1, 'Al Ruqi'),(76, 2, 'الرقعي'),(77, 1, 'Hafar Al Baten'),(77, 2, 'حفر الباطن'),(78, 1, 'King Khalid City'),(78, 2, 'مدينة الملك خالد العسكرية '),(79, 1, 'Qaysumah'),(79, 2, 'القيصومة'),(80, 1, 'Rafha'),(80, 2, 'رفحاء'),(81, 1, 'Sarrar'),(81, 2, 'السرار'),(82, 1, 'Al Ahsa'),(82, 2, 'الإحساء'),(83, 1, 'Al Ayun'),(83, 2, 'العيون'),(84, 1, 'Al Jafr'),(84, 2, 'الجفر'),(85, 1, 'Batha'),(85, 2, 'البطحاء'),(86, 1, 'Hufuf'),(86, 2, 'الهفوف'),(87, 1, 'Mubarraz'),(87, 2, 'المبرز'),(88, 1, 'Salwa'),(88, 2, 'سلوى'),(89, 1, 'Badr'),(89, 2, 'بدر'),(90, 1, 'Bahrah'),(90, 2, 'بحرة'),(91, 1, 'Jeddah'),(91, 2, 'جدة'),(92, 1, 'Jeddah Airport'),(92, 2, 'جدة مطار الملك عبد العزيز '),(93, 1, 'Kamil'),(93, 2, 'كامل'),(94, 1, 'Khulais'),(94, 2, 'خليص'),(95, 1, 'Lith'),(95, 2, 'الليث'),(96, 1, 'Masturah'),(96, 2, 'مستورة'),(97, 1, 'Rabigh'),(97, 2, 'رابغ'),(98, 1, 'Shaibah'),(98, 2, 'الشعيبة'),(99, 1, 'Thuwal'),(99, 2, 'ثول'),(100, 1, 'Abha'),(100, 2, 'أبها'),(101, 1, 'Ahad Rafidah'),(101, 2, 'أحد الرفيدة '),(102, 2, 'بارق'),(102, 1, 'Bariq'),(103, 1, 'Bishah'),(103, 2, 'بيشة'),(104, 1, 'Dhahran Al Janoub'),(104, 2, 'ظهران الجنوب'),(105, 1, 'Jash'),(105, 2, 'جاش'),(106, 1, 'Khamis Mushayt'),(106, 2, 'خميس مشيط'),(107, 1, 'Majardah'),(107, 2, 'مجاردة'),(108, 1, 'Muhayil '),(108, 2, 'محايل'),(109, 1, 'Nakeea'),(109, 2, 'النقيع '),(110, 1, 'Rijal Almaa'),(110, 2, 'رجال ألمع'),(111, 1, 'Sarat Abida'),(111, 2, 'سراة عبيدة '),(112, 1, 'Tarib'),(112, 2, 'طريب'),(113, 1, 'Tathlith'),(113, 2, 'تثليث'),(114, 1, 'Jamoum'),(114, 2, 'الجموم'),(115, 1, 'Makkah'),(115, 2, 'مكة المكرمة '),(116, 2, 'الطائف'),(116, 1, 'Taif'),(117, 1, 'Hanakiyah'),(117, 2, 'الحناكية '),(118, 1, 'Khayber'),(118, 2, 'خيبر'),(119, 1, 'Madinah'),(119, 2, 'المدينة المنورة'),(120, 1, 'Mahd Ad Dhahab'),(120, 2, 'مهد الذهب'),(121, 1, 'Ula'),(121, 2, 'العلا'),(122, 1, 'Afif'),(122, 2, 'عفيف'),(123, 1, 'Artawiyah'),(123, 2, 'الأرطاوية'),(124, 1, 'Bijadiyah'),(124, 2, 'البجادية'),(125, 1, 'Duwadimi'),(125, 2, 'الدوادمي'),(126, 1, 'Ghat'),(126, 2, 'الغاط'),(127, 1, 'Hawtat Sudayr '),(127, 2, 'حوطة سدير'),(128, 1, 'Majmaah'),(128, 2, 'المجمعة'),(129, 1, 'Shaqra'),(129, 2, 'شقراء'),(130, 1, 'Zulfi'),(130, 2, 'الزلفي '),(131, 1, 'Arar'),(131, 2, 'عرعر'),(132, 1, 'Jadidah Arar'),(132, 2, 'جديدة عرعر'),(133, 1, 'Al Aflaj (Layla)'),(133, 2, 'الأفلاج'),(134, 1, 'Dhurma'),(134, 2, 'ضرما'),(135, 1, 'Dilam'),(135, 2, 'الدلم'),(136, 1, 'Diriyah'),(136, 2, 'الدرعية'),(137, 1, 'Hawtat Bani Tamim'),(137, 2, 'حوطة بني تميم'),(138, 1, 'Hayer'),(138, 2, 'الحائر'),(139, 1, 'Huraymila'),(139, 2, 'حريملاء'),(140, 1, 'Kharj'),(140, 2, 'الخرج'),(141, 1, 'Muzahmiyah'),(141, 2, 'المزاحمية'),(142, 1, 'Quwayiyah'),(142, 2, 'القويعية'),(143, 1, 'Rayn'),(143, 2, 'الرين'),(144, 1, 'Riyadh'),(144, 2, 'الرياض'),(145, 1, 'Riyadh Airport'),(145, 2, 'مطار الملك خالد الرياض'),(146, 1, 'Rumah'),(146, 2, 'رماح'),(147, 1, 'Ruwaidah'),(147, 2, 'الرويضة'),(148, 1, 'Dhalim'),(148, 2, 'ظلم'),(149, 1, 'Khurmah'),(149, 2, 'الخرمة'),(150, 1, 'Muwayh'),(150, 2, 'المويه'),(151, 1, 'Ranyah'),(151, 2, 'رينة'),(152, 1, 'Sayl Al Kabir'),(152, 2, 'السيل الكبير '),(153, 1, 'Turbah'),(153, 2, 'تربة'),(154, 1, 'Turbah (Makkah)'),(154, 2, 'تربة مكة'),(155, 1, 'Dhuba'),(155, 2, 'ضبا'),(156, 1, 'Halit Ammar'),(156, 2, 'حالة عمار'),(157, 1, 'Haql'),(157, 2, 'حقل '),(158, 1, 'Tabuk'),(158, 2, 'تبوك'),(159, 1, 'Taima'),(159, 2, 'تيماء '),(160, 2, 'منفذ الحديثة'),(160, 1, 'Haditha'),(161, 2, 'القريات'),(161, 1, 'Qurayyat'),(162, 1, 'Tabarjal'),(162, 2, 'طبرجل'),(163, 1, 'Turayf'),(163, 2, 'طريف'),(164, 1, 'Khamasin'),(164, 2, 'الخماسين'),(165, 1, 'Sulayyil'),(165, 2, 'السليل'),(166, 1, 'Badar Hunain'),(166, 2, 'بدر حنين'),(167, 1, 'Ummlujj'),(167, 2, 'أملج'),(168, 1, 'Wajh'),(168, 2, 'الوجة'),(169, 1, 'Yanbu'),(169, 2, 'ينبع')");
    }

    /**
     * Delete plugin database tables
     *
     * @return void
     */
    public static function delete_tables() {
        global $table_prefix, $wpdb;

        $wp_smsa_table = $table_prefix . 'smsa';
        $wp_smsa_city_table = $table_prefix . 'smsa_city';

        $wpdb->query("DROP TABLE IF EXISTS {$wp_smsa_table}");
        $wpdb->query("DROP TABLE IF EXISTS {$wp_smsa_city_table}");
        delete_option('my_plugin_db_version');
    }
}
