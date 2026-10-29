<?php

declare(strict_types=1);

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

namespace ILIAS\Style\Content;

use ilDBInterface;
use ilObjStyleSheet;

/**
 * @author Alexander Killing <killing@leifos.de>
 */
class CharacteristicDBRepo
{
    protected ilDBInterface $db;
    protected InternalDataService $factory;

    /**
     * Characteristic records (incl. titles) per style, in "order_nr, type, characteristic" order.
     * The page editor resolves hundreds of characteristics per request, so all records of a
     * style are read with two queries and served from here.
     * @var array<int, array{list: array[], keys: array<string, int>}>
     */
    protected static array $cache = [];

    public function __construct(
        ilDBInterface $db,
        InternalDataService $factory
    ) {
        $this->db = $db;
        $this->factory = $factory;
    }

    /**
     * Invalidates the cached characteristics of a style (or all styles). Called
     * whenever style_char or style_char_title is written; this also drops the
     * shared ilObjStyleSheet lookup instance, which holds the same data.
     */
    public static function clearCache(?int $style_id = null): void
    {
        if (is_null($style_id)) {
            self::$cache = [];
        } else {
            unset(self::$cache[$style_id]);
        }
        ilObjStyleSheet::clearLookupInstance($style_id);
    }

    /**
     * Case-insensitive lookup key, mirroring the general_ci collation of style_char
     * for ASCII names (characteristic and type names are restricted to ASCII).
     */
    protected static function cacheKey(string $type, string $characteristic): string
    {
        return mb_strtolower($type) . ":" . mb_strtolower($characteristic);
    }

    /**
     * @return array{list: array[], keys: array<string, int>}
     */
    protected function getCachedRecords(int $style_id): array
    {
        if (isset(self::$cache[$style_id])) {
            return self::$cache[$style_id];
        }
        $db = $this->db;

        $list = [];
        $keys = [];
        $set = $db->queryF(
            "SELECT * FROM style_char " .
            " WHERE style_id = %s " .
            " ORDER BY order_nr, type, characteristic",
            ["integer"],
            [$style_id]
        );
        while ($rec = $db->fetchAssoc($set)) {
            $rec["titles"] = [];
            $keys[self::cacheKey($rec["type"], $rec["characteristic"])] = count($list);
            $list[] = $rec;
        }

        $set = $db->queryF(
            "SELECT * FROM style_char_title " .
            " WHERE style_id = %s ",
            ["integer"],
            [$style_id]
        );
        while ($rec = $db->fetchAssoc($set)) {
            $i = $keys[self::cacheKey($rec["type"], $rec["characteristic"])] ?? null;
            if (!is_null($i)) {
                $list[$i]["titles"][$rec["lang"]] = $rec["title"];
            }
        }

        return self::$cache[$style_id] = ["list" => $list, "keys" => $keys];
    }

    protected function getCharacteristicFromRecord(int $style_id, array $rec): Characteristic
    {
        return $this->factory->characteristic(
            $rec["type"],
            $rec["characteristic"],
            (bool) $rec["hide"],
            $rec["titles"],
            $style_id,
            (int) $rec["order_nr"],
            (bool) $rec["outdated"]
        );
    }

    public function addCharacteristic(
        int $style_id,
        string $type,
        string $char,
        bool $hidden = false,
        int $order_nr = 0,
        bool $outdated = false
    ): void {
        self::clearCache($style_id);
        $db = $this->db;

        $db->insert("style_char", [
            "style_id" => ["integer", $style_id],
            "type" => ["text", $type],
            "characteristic" => ["text", $char],
            "hide" => ["integer", $hidden],
            "order_nr" => ["integer", $order_nr],
            "outdated" => ["integer", $outdated]
        ]);
    }

    public function exists(
        int $style_id,
        string $type,
        string $char
    ): bool {
        return isset($this->getCachedRecords($style_id)["keys"][self::cacheKey($type, $char)]);
    }

    public function getByKey(
        int $style_id,
        string $type,
        string $characteristic
    ): ?Characteristic {
        $records = $this->getCachedRecords($style_id);
        $i = $records["keys"][self::cacheKey($type, $characteristic)] ?? null;
        if (is_null($i)) {
            return null;
        }
        $rec = $records["list"][$i];
        return $this->factory->characteristic(
            $type,
            $characteristic,
            (bool) $rec["hide"],
            $rec["titles"],
            $style_id,
            (int) $rec["order_nr"],
            (bool) $rec["outdated"]
        );
    }

    public function getByType(
        int $style_id,
        string $type
    ): array {
        return $this->getByTypes(
            $style_id,
            [$type]
        );
    }

    public function getByTypes(
        int $style_id,
        array $types,
        bool $include_hidden = true,
        bool $include_outdated = true
    ): array {
        $types = array_map("mb_strtolower", $types);
        $chars = [];
        foreach ($this->getCachedRecords($style_id)["list"] as $rec) {
            if (!in_array(mb_strtolower($rec["type"]), $types, true)) {
                continue;
            }
            if (($rec["hide"] && !$include_hidden) ||
                ($rec["outdated"] && !$include_outdated)) {
                continue;
            }
            $chars[] = $this->getCharacteristicFromRecord($style_id, $rec);
        }
        return $chars;
    }

    /**
     * Get characteristics by supertype
     * @return Characteristic[]
     */
    public function getBySuperType(
        int $style_id,
        string $super_type
    ): array {
        $stypes = ilObjStyleSheet::_getStyleSuperTypes();
        $types = $stypes[$super_type];

        return $this->getByTypes(
            $style_id,
            $types
        );
    }


    /**
     * Save titles for characteristic
     */
    public function saveTitles(
        int $style_id,
        string $type,
        string $characteristic,
        array $titles
    ): void {
        self::clearCache($style_id);
        $db = $this->db;

        $db->manipulateF(
            "DELETE FROM style_char_title " .
            " WHERE style_id = %s AND type = %s AND characteristic = %s ",
            ["integer", "text", "text"],
            [$style_id, $type, $characteristic]
        );

        foreach ($titles as $l => $title) {
            $this->addTitle(
                $style_id,
                $type,
                $characteristic,
                $l,
                $title
            );
        }
    }

    public function addTitle(
        int $style_id,
        string $type,
        string $characteristic,
        string $lang,
        string $title
    ): void {
        self::clearCache($style_id);
        $db = $this->db;

        $db->insert("style_char_title", [
            "style_id" => ["integer", $style_id],
            "type" => ["text", $type],
            "characteristic" => ["text", $characteristic],
            "lang" => ["text", $lang],
            "title" => ["text", $title]
        ]);
    }


    public function cloneAllFromStyle(
        int $from_style_id,
        int $to_style_id
    ): void {
        self::clearCache($to_style_id);
        $set = $this->db->queryF(
            "SELECT * FROM style_char " .
            " WHERE style_id = %s ",
            ["integer"],
            [$from_style_id]
        );
        while ($rec = $this->db->fetchAssoc($set)) {
            $this->db->insert("style_char", [
                "style_id" => ["integer", $to_style_id],
                "type" => ["text", $rec["type"]],
                "characteristic" => ["text", $rec["characteristic"]],
                "hide" => ["integer", (int) ($rec["hide"] ?? 0)],
                "outdated" => ["integer", (int) ($rec["outdated"] ?? 0)],
                "order_nr" => ["integer", (int) ($rec["order_nr"] ?? 0)]
            ]);
        }
        $set = $this->db->queryF(
            "SELECT * FROM style_char_title " .
            " WHERE style_id = %s ",
            ["integer"],
            [$from_style_id]
        );
        while ($rec = $this->db->fetchAssoc($set)) {
            $this->db->insert("style_char_title", [
                "style_id" => ["integer", $to_style_id],
                "type" => ["text", $rec["type"]],
                "characteristic" => ["text", $rec["characteristic"]],
                "lang" => ["text", $rec["lang"]],
                "title" => ["text", $rec["title"]]
            ]);
        }
    }

    /**
     * Save characteristic hidden status
     */
    public function saveHidden(
        int $style_id,
        string $type,
        string $characteristic,
        bool $hide
    ): void {
        self::clearCache($style_id);
        $db = $this->db;

        $db->update(
            "style_char",
            [
            "hide" => ["integer", $hide]
        ],
            [    // where
                "style_id" => ["integer", $style_id],
                "type" => ["text", $type],
                "characteristic" => ["text", $characteristic]
            ]
        );
    }

    /**
     * Save characteristic outdated status
     */
    public function saveOutdated(
        int $style_id,
        string $type,
        string $characteristic,
        bool $outdated
    ): void {
        self::clearCache($style_id);
        $db = $this->db;

        $db->update(
            "style_char",
            [
            "outdated" => ["integer", $outdated]
        ],
            [    // where
                "style_id" => ["integer", $style_id],
                "type" => ["text", $type],
                "characteristic" => ["text", $characteristic]
            ]
        );
    }

    public function isOutdated(
        int $style_id,
        string $type,
        string $characteristic
    ): bool {
        $records = $this->getCachedRecords($style_id);
        $i = $records["keys"][self::cacheKey($type, $characteristic)] ?? null;
        return !is_null($i) && $records["list"][$i]["outdated"];
    }

    public function saveOrderNr(
        int $style_id,
        string $type,
        string $characteristic,
        int $order_nr
    ): void {
        self::clearCache($style_id);
        $db = $this->db;

        $db->update(
            "style_char",
            [
            "order_nr" => ["integer", $order_nr]
        ],
            [    // where
                "style_id" => ["integer", $style_id],
                "type" => ["text", $type],
                "characteristic" => ["text", $characteristic]
            ]
        );
    }

    public function deleteCharacteristic(
        int $style_id,
        string $type,
        string $tag,
        string $class
    ): void {
        self::clearCache($style_id);
        $db = $this->db;

        // delete characteristic record
        $db->manipulateF(
            "DELETE FROM style_char WHERE style_id = %s AND type = %s AND characteristic = %s",
            array("integer", "text", "text"),
            array($style_id, $type, $class)
        );

        // delete parameter records
        $db->manipulateF(
            "DELETE FROM style_parameter WHERE style_id = %s AND tag = %s AND type = %s AND class = %s",
            array("integer", "text", "text", "text"),
            array($style_id, $tag, $type, $class)
        );
    }

    //
    // Parameter
    //

    public function replaceParameter(
        int $style_id,
        string $a_tag,
        string $a_class,
        string $a_par,
        string $a_val,
        string $a_type,
        int $a_mq_id = 0,
        bool $a_custom = false
    ): void {
        $db = $this->db;

        $set = $db->queryF(
            "SELECT * FROM style_parameter " .
            " WHERE style_id = %s AND tag = %s AND class = %s AND mq_id = %s " .
            " AND custom = %s AND type = %s AND parameter = %s ",
            ["integer", "text", "text", "integer", "integer", "text", "text"],
            [$style_id, $a_tag, $a_class, $a_mq_id, $a_custom, $a_type, $a_par]
        );

        if ($set->fetchRow()) {
            $db->update(
                "style_parameter",
                [
                "value" => ["text", $a_val]
            ],
                [    // where
                    "style_id" => ["integer", $style_id],
                    "tag" => ["text", $a_tag],
                    "class" => ["text", $a_class],
                    "mq_id" => ["integer", $a_mq_id],
                    "custom" => ["integer", $a_custom],
                    "type" => ["text", $a_type],
                    "parameter" => ["text", $a_par]
                ]
            );
        } else {
            $id = $db->nextId("style_parameter");
            $db->insert("style_parameter", [
                "id" => ["integer", $id],
                "value" => ["text", $a_val],
                "style_id" => ["integer", $style_id],
                "tag" => ["text", $a_tag],
                "class" => ["text", $a_class],
                "type" => ["text", $a_type],
                "parameter" => ["text", $a_par],
                "mq_id" => ["integer", $a_mq_id],
                "custom" => ["integer", $a_custom]
            ]);
        }
    }

    public function getAllParametersOfCharacteristic(
        int $style_id,
        string $type,
        string $characteristic
    ): array {
        $set = $this->db->queryF(
            "SELECT * FROM style_parameter " .
            " WHERE style_id = %s AND class = %s AND type = %s",
            ["integer", "string", "string"],
            [$style_id, $characteristic, $type]
        );
        $data = [];
        while ($rec = $this->db->fetchAssoc($set)) {
            $data[] = $rec;
        }
        return $data;
    }

    public function deleteParameter(
        int $style_id,
        string $tag,
        string $class,
        string $par,
        string $type,
        int $mq_id = 0,
        bool $custom = false
    ): void {
        $db = $this->db;

        $q = "DELETE FROM style_parameter WHERE " .
            " style_id = " . $db->quote($style_id, "integer") . " AND " .
            " tag = " . $db->quote($tag, "text") . " AND " .
            " class = " . $db->quote($class, "text") . " AND " .
            " mq_id = " . $db->quote($mq_id, "integer") . " AND " .
            " custom = " . $db->quote($custom, "integer") . " AND " .
            " " . $db->equals("type", $type, "text", true) . " AND " .
            " parameter = " . $db->quote($par, "text");

        $db->manipulate($q);
    }

    public function updateColorName(
        int $style_id,
        string $old_name,
        string $new_name
    ): void {
        if ($old_name == $new_name) {
            return;
        }

        $db = $this->db;

        $color_attributes = [
            "background-color",
            "color",
            "border-color",
            "border-top-color",
            "border-bottom-color",
            "border-left-color",
            "border-right-color",
        ];

        $set = $db->queryF(
            "SELECT * FROM style_parameter " .
            " WHERE style_id = %s AND " . $db->in("parameter", $color_attributes, false, "text"),
            ["integer"],
            [$style_id]
        );
        while ($rec = $db->fetchAssoc($set)) {
            if ($rec["value"] == "!" . $old_name ||
                is_int(strpos($rec["value"], "!" . $old_name . "("))) {
                // parameter is based on color -> rename it
                $this->replaceParameter(
                    $style_id,
                    $rec["tag"],
                    $rec["class"],
                    $rec["parameter"],
                    str_replace($old_name, $new_name, $rec["value"]),
                    $rec["type"],
                    $rec["mq_id"],
                    (bool) $rec["custom"]
                );
            }
        }
    }
}
