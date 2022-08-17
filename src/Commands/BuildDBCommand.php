<?php

namespace Robosys\DBScaffolder\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BuildDBCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'magic {sheet? : The google sheet ID to build from} {range? : The sheet range e.g. "Sheet1!A1:AT21"} {--scaffold}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Builds the model schemas from the given google sheet';

    protected $relations = "";

    protected $relationKeys = [];
    protected $relationIntKeys = [];

    protected $prepend = <<<END
    [{
        "name": "id",
        "dbType": "bigIncrements",
        "htmlType": null,
        "validations": null,
        "searchable": false,
        "fillable": false,
        "primary": true,
        "inForm": false,
        "inIndex": false,
        "inView": false
    },
    END;
    protected $append = <<<END
    {
        "name": "created_at",
        "dbType": "timestamp",
        "htmlType": null,
        "validations": null,
        "searchable": false,
        "fillable": false,
        "primary": false,
        "inForm": false,
        "inIndex": false,
        "inView": true
    },
    {
        "name": "updated_at",
        "dbType": "timestamp",
        "htmlType": null,
        "validations": null,
        "searchable": false,
        "fillable": false,
        "primary": false,
        "inForm": false,
        "inIndex": false,
        "inView": true
    },
    END;

    protected $finalize = ']';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $scaffold = $this->option('scaffold') ?: false;
        if ($scaffold === true) {
            return $this->scaffold();
        }
        $sheet = $this->argument('sheet') ?: false;
        $pos = strpos($sheet, "sheet=");
        if ($pos !== false) {
            $sheet = substr($sheet, 6);
        }
        $range = $this->argument('range') ?: false;
        $pos = strpos($range, "range=");
        if ($pos !== false) {
            $range = substr($range, 6);
        }
        //todo
        $this->generate($sheet, $range);

        return 0;
    }

    public function scaffold()
    {
        $files = robosys_get_Files("resources/model_schemas");
        foreach ($files as $f) {
            $model = $f;
            $path = "resources/model_schemas/{$model}.json";
            $this->info("scaffolding... $model");
            $out = shell_exec('"' . base_path('vendor/robosys-labs/db-scaffolder/fun.cmd') . '"' . " $model $path");
            $this->info($out);
        }
    }

    public function generate($sheet, $range)
    {
        $table = robosys_google_sheet($sheet, $range);
        $this->info("Data loaded...");
        $i = 0;
        $column = array_column($table, $i);
        $tableName = "";

        while (!empty($column)) {
            $i++;
            $namefield = "";
            foreach ($column as $idx => $field) {
                if ($idx == 0) {
                    $namefield = Str::singular($field);
                    $tableName = $field;
                    $this->info("Processing relations for table $tableName");
                    if (!isset($this->relationKeys[$namefield])) {
                        $this->relationKeys[$namefield] = [];
                        $this->relationIntKeys[$namefield] = [];
                    }
                    continue;
                }
                foreach ($this->relationKeys as $k => $v) {
                    //k=user
                    $relField = $k . "_id";
                    $d = explode('_', $namefield);
                    //user_id
                    //relpivot=user_interest_id
                    //namefield = bookmark
                    //field=user_id
                    if ($relField == $field) {
                        //field is user_id
                        //field contains user
                        //column contains pivot
                        if (isset($d[1])) {
                            $relPivotField1 = $d[0] . "_id";
                            $relPivotField2 = $d[1] . "_id";
                            if (in_array($relPivotField1, $column) && in_array($relPivotField2, $column)) {
                                $this->relationIntKeys[$k][$relPivotField2] = $tableName;
                            }
                        } else {
                            if (!empty($field) && strpos($field, '_id') !== false && !in_array($field, $v)) {
                                $this->relationKeys[$k][] = $namefield;
                            }
                        }
                    }
                }
            }
            $column = array_column($table, $i);
        }
        $i = 0;
        $column = array_column($table, $i);
        while (!empty($column)) {
            $i++;
            $this->relations = "";
            $entry = "";
            $namefield = "";
            foreach ($column as $idx => $field) {
                if ($idx == 0) {
                    $namefield = Str::singular($field);
                    $tableName = $field;
                    $this->info("Processing  for table $tableName");
                    continue;
                }
                if (empty($field) || $field == "id" || $field == 'created_at' || $field == 'updated_at') {
                    continue;
                }
                $entry .= $this->buildEntry($field, $tableName);
            }
            if (isset($this->relationKeys[$namefield])) {
                foreach ($this->relationKeys[$namefield] as $rel) {
                    $idf = $namefield . "_id";
                    $this->add1Tm($idf, $rel);
                }
            }
            if (isset($this->relationIntKeys[$namefield])) {
                foreach ($this->relationIntKeys[$namefield] as $k => $rel) {
                    $this->addmTm($namefield, $k, $rel);
                }
            }
            $titled = str_replace('_', '', Str::title($namefield));
            $filename = resource_path('model_schemas/') . $titled . '.json';
            $contents = $this->prepend . $entry . $this->append . $this->relations . $this->finalize;
            $contents = Str::replaceLast('},]', '}]', $contents);
            file_put_contents($filename, $contents);
            $column = array_column($table, $i);
        }
        $this->info("Done...");
    }

    public function buildEntry($fieldName, $tableName)
    {
        if (strpos($fieldName, '_id') !== false) {
            $plural = $tableName;
            if (strpos($fieldName, 'parent') !== false) {
                $this->addmt1($fieldName, $tableName);
            } else {
                $this->addmt1($fieldName);
                $plural = Str::plural(substr($fieldName, 0, strrpos($fieldName, '_')));
            }
            $ref = "name";
            if ($plural == "users") {
                $ref = "email";
            }
            return <<<END
        {
            "name": "$fieldName",
            "dbType": "bigInteger:unsigned:nullable:foreign,$plural,id",
            "htmlType": "selectTable:$plural:$ref,id",
            "validations": "exists:$plural,id",
            "searchable": true,
            "fillable": true,
            "primary": false,
            "inForm": true,
            "inIndex": true,
            "inView": true
        },
        END;
        } elseif (Str::contains($fieldName, ['is_', '_must_', 'has_', 'autoplay_'])) {
            return <<<END
        {
            "name": "$fieldName",
            "dbType": "boolean:default,0",
            "htmlType": "checkbox",
            "validations": "boolean",
            "searchable": true,
            "fillable": true,
            "primary": false,
            "inForm": true,
            "inIndex": true,
            "inView": true
        },
        END;
        } elseif (Str::contains($fieldName, ['_time', '_at', 'last_login', 'last_active'])) {
            return <<<END
        {
            "name": "$fieldName",
            "dbType": "datetime:nullable",
            "htmlType": "date",
            "validations": "",
            "searchable": true,
            "fillable": true,
            "primary": false,
            "inForm": true,
            "inIndex": true,
            "inView": true
        },
        END;
        } elseif (Str::contains($fieldName, ['prefer'])) {
            return <<<END
        {
            "name": "$fieldName",
            "dbType": "boolean:nullable",
            "htmlType": "checkbox",
            "validations": "boolean|null",
            "searchable": true,
            "fillable": true,
            "primary": false,
            "inForm": true,
            "inIndex": true,
            "inView": true
        },
        END;
        } elseif (Str::contains($fieldName, ['type', 'level', 'freq', 'status', 'sort']) && !Str::contains($fieldName, ['amount', 'price', 'cost', 'credit', 'debit'])) {
            return <<<END
        {
            "name": "$fieldName",
            "dbType": "tinyInteger:unsigned:nullable",
            "htmlType": "number",
            "validations": "numeric|max:127",
            "searchable": true,
            "fillable": true,
            "primary": false,
            "inForm": true,
            "inIndex": true,
            "inView": true
        },
        END;
        } elseif (Str::contains($fieldName, ['_num', '_max', 'max_', '_min', 'min_', 'minimum_', 'maximum_', 'total_', '_count', 'point', 'views', 'discount', 'percent', 'score']) && !Str::contains($fieldName, ['amount', 'price', 'cost', 'credit', 'debit'])) {
            return <<<END
        {
            "name": "$fieldName",
            "dbType": "integer:unsigned:nullable",
            "htmlType": "number",
            "validations": "numeric",
            "searchable": true,
            "fillable": true,
            "primary": false,
            "inForm": true,
            "inIndex": true,
            "inView": true
        },
        END;
        } elseif (Str::contains($fieldName, ['reason', 'purpose', 'description', 'summary', 'comment', '_uri'])) {
            return <<<END
        {
            "name": "$fieldName",
            "dbType": "string,500:nullable",
            "htmlType": "textarea",
            "validations": "max:500:min:10:nullable",
            "searchable": true,
            "fillable": true,
            "primary": false,
            "inForm": true,
            "inIndex": true,
            "inView": true
        },
        END;
        } elseif (Str::contains($fieldName, ['first_name', 'last_name', 'label', 'code'])) {
            return <<<END
        {
            "name": "$fieldName",
            "dbType": "string,35:nullable",
            "htmlType": "text",
            "validations": "max:35",
            "searchable": true,
            "fillable": true,
            "primary": false,
            "inForm": true,
            "inIndex": true,
            "inView": true
        },
        END;
        } elseif (Str::contains($fieldName, ['phone', 'mobile'])) {
            return <<<END
        {
            "name": "$fieldName",
            "dbType": "string,15:nullable",
            "htmlType": "text",
            "validations": "digits_between:9,15",
            "searchable": true,
            "fillable": true,
            "primary": false,
            "inForm": true,
            "inIndex": true,
            "inView": true
        },
        END;
        } elseif (Str::contains($fieldName, ['email'])) {
            return <<<END
        {
            "name": "$fieldName",
            "dbType": "string,50:nullable",
            "htmlType": "text",
            "validations": "email|max:50",
            "searchable": true,
            "fillable": true,
            "primary": false,
            "inForm": true,
            "inIndex": true,
            "inView": true
        },
        END;
        } elseif (Str::contains($fieldName, ['color'])) {
            return <<<END
        {
            "name": "$fieldName",
            "dbType": "char,6:nullable",
            "htmlType": "text",
            "validations": "min:6|max:6",
            "searchable": true,
            "fillable": true,
            "primary": false,
            "inForm": true,
            "inIndex": true,
            "inView": true
        },
        END;
        } elseif (Str::contains($fieldName, ['_date', 'birthday'])) {
            return <<<END
        {
            "name": "$fieldName",
            "dbType": "date:nullable",
            "htmlType": "date",
            "validations": "",
            "searchable": true,
            "fillable": true,
            "primary": false,
            "inForm": true,
            "inIndex": true,
            "inView": true
        },
        END;
        } elseif (Str::contains($fieldName, ['balance', 'wallet', 'amount', 'cost', 'price', 'credit', 'debit'])) {
            return <<<END
        {
            "name": "$fieldName",
            "dbType": "decimal,18,2:unsigned:nullable",
            "htmlType": "text",
            "validations": "numeric",
            "searchable": true,
            "fillable": true,
            "primary": false,
            "inForm": true,
            "inIndex": true,
            "inView": true
        },
        END;
        } elseif (Str::contains($fieldName, ['password'])) {
            return <<<END
        {
            "name": "$fieldName",
            "dbType": "string,64:nullable",
            "htmlType": "text",
            "validations": "required|regex:/^(?=.*[0-9])(?=.*[\\\\D])(.+)$/|min:8",
            "searchable": false,
            "fillable": true,
            "primary": false,
            "inForm": true,
            "inIndex": false,
            "inView": false
        },
        END;
        } elseif (Str::contains($fieldName, ['title', 'caption', 'subtitle', 'names', 'token'])) {
            return <<<END
        {
            "name": "$fieldName",
            "dbType": "string,100:nullable",
            "htmlType": "text",
            "validations": "max:100",
            "searchable": true,
            "fillable": true,
            "primary": false,
            "inForm": true,
            "inIndex": true,
            "inView": true
        },
        END;
        } elseif (Str::contains($fieldName, ['name', 'other_names'])) {
            return <<<END
        {
            "name": "$fieldName",
            "dbType": "string,50:nullable",
            "htmlType": "text",
            "validations": "max:50",
            "searchable": true,
            "fillable": true,
            "primary": false,
            "inForm": true,
            "inIndex": true,
            "inView": true
        },
    END;
        } elseif (Str::contains($fieldName, ['_file', 'file_', 'image_', 'picture_', 'document'])) {
            return <<<END
        {
            "name": "$fieldName",
            "dbType": "string,255:nullable",
            "htmlType": "file",
            "validations": "max:10240|mimes:jpg,bmp,png,pdf,docx,xls,xlsx,jpeg,csv",
            "searchable": true,
            "fillable": true,
            "primary": false,
            "inForm": true,
            "inIndex": true,
            "inView": true
        },
        END;
        } elseif (Str::contains($fieldName, ['_url', 'website', 'domain'])) {
            return <<<END
        {
            "name": "$fieldName",
            "dbType": "string,255:nullable",
            "htmlType": "text",
            "validations": "max:255",
            "searchable": true,
            "fillable": true,
            "primary": false,
            "inForm": true,
            "inIndex": true,
            "inView": true
        },
        END;
        } elseif (strpos($fieldName, 'ip_address') !== false) {
            return <<<END
        {
            "name": "$fieldName",
            "dbType": "char,15:nullable",
            "htmlType": "text",
            "validations": "ip",
            "searchable": true,
            "fillable": true,
            "primary": false,
            "inForm": true,
            "inIndex": true,
            "inView": true
        },
        END;
        } elseif (strpos($fieldName, 'text') !== false) {
            return <<<END
        {
            "name": "$fieldName",
            "dbType": "text",
            "htmlType": "textarea",
            "validations": "",
            "searchable": true,
            "fillable": true,
            "primary": false,
            "inForm": true,
            "inIndex": true,
            "inView": true
        },
        END;
        } elseif (Str::contains($fieldName, ['currency'])) {
            return <<<END
        {
            "name": "$fieldName",
            "dbType": "char,6:nullable",
            "htmlType": "text",
            "validations": "min:6|max:6",
            "searchable": true,
            "fillable": true,
            "primary": false,
            "inForm": true,
            "inIndex": true,
            "inView": true
        },
        END;
        } else {
            return <<<END
        {
            "name": "$fieldName",
            "dbType": "string,255:nullable",
            "htmlType": "text",
            "validations": "",
            "searchable": true,
            "fillable": true,
            "primary": false,
            "inForm": true,
            "inIndex": true,
            "inView": true
        },
        END;
        }
    }

    public function addmt1($field, $tableName = null)
    {
        if ($tableName) {
            $camel = Str::title(substr($tableName, 0, strrpos($tableName, '_')));
        } else {
            $camel = Str::title(substr($field, 0, strrpos($field, '_')));
        }
        $camel = str_replace('_', '', $camel);
        $this->relations .=  <<<END
        {
            "name": "$camel",
            "dbType": "relation",
            "relation": "mt1,$camel,$field,id"
        },
        END;
    }

    public function add1Tm($field, $tableName)
    {
        $camel = Str::title(str_replace('_', '', $tableName));
        $plural = Str::plural($camel);
        $this->relations .=  <<<END
        {
            "name": "$plural",
            "dbType": "relation",
            "relation": "1tm,$camel,$field,id"
        },
        END;
    }

    public function addmTm($field, $modelField, $pivot)
    {
        $camel = Str::title(substr($modelField, 0, strrpos($modelField, '_')));
        $camel = str_replace('_', '', $camel);
        $plural = Str::plural(Str::title($camel));
        $this->relations .=  <<<END
        {
            "name": "$plural",
            "dbType": "relation",
            "relation": "mtm,$camel,$pivot"
        },
        END;
    }
}
