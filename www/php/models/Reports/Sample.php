<?php
/*
Sample report class

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class ReportSample extends Reports {

    public function __construct() {
        parent::__construct();
    }

    public function getReportFilters() {
        $this->f = array_merge($this->f, array(
            #add there data for custom filters
            #'select_users' => Users::i()->listSelectOptions(),
            'is_dates' => $this->f['from_date'] || $this->f['to_date'] ? 1 : 0
        ));
        return $this->f;
    }

    public function getReportData($value = '') {
        $ps = array();

        #apply filters from Me.f
        $where = ' ';
        if ($this->f['from_date']) {
            $where .= ' and el.add_time>=' . dbq(DateUtils::Str2SQL($this->f['from_date']));
        }
        if ($this->f['to_date']) {
            $where .= ' and el.add_time<DATE_ADD(' . dbq(DateUtils::Str2SQL($this->f['to_date'])) . ', INTERVAL 1 DAY)'; #+1 because less than equal
        }

        #define query
        $evmodel     = FwEvents::i();
        $sql         = "SELECT el.*, e.iname as event_name, u.fname, u.lname, 1 as ctr
                  FROM $evmodel->table_name e, $evmodel->log_table_name el
                       LEFT OUTER JOIN users u ON (u.id=el.add_users_id)
                 WHERE el.events_id=e.id
                   $where
                ORDER by el.id desc
                LIMIT 50 ";
        $ps['rows']  = $this->db->arr($sql);
        $ps['count'] = count($ps['rows']);

        #perform calculations and add additional info for each result row
        /*
                foreach ($ps['rows'] as &$row) {
                    $row['event'] = $evmodel->one($row['events_id']);
                }
                unset($row);
                $ps["total_ctr"] = $this->_calcPerc($ps["rows"], 'ctr', 'perc'); #if you need calculate "perc" for each row based on each $row["ctr"]
        */
        return $ps;
        #hint: use <~rep[rows]> and <~f[from_date]> in /admin/reports/sample/report_html.html
    }
}
