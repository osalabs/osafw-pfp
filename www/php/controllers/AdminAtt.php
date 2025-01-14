<?php
/*
Admin Att Controller class

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class AdminAttController extends FwAdminController {
    const int    access_level         = Users::ACL_MANAGER;
    const string route_default_action = '';

    public FwModel|Att $model;
    public string $model_name = 'Att';

    public string $base_url = '/Admin/Att';
    public string $required_fields = 'iname';
    public string $save_fields = 'att_categories_id iname status';

    /*REMOVE OR OVERRIDE*/
    public string $search_fields = 'iname idesc';
    public string $list_sortdef = 'iname asc';   //default sorting - req param name, asc|desc direction
    public array $list_sortmap = array( //sorting map: req param name => sql field name(s) asc|desc direction
                                        'id'       => 'id',
                                        'iname'    => 'iname',
                                        'add_time' => 'add_time',
                                        'fsize'    => 'fsize',
                                        'ext'      => 'ext',
                                        'category' => 'att_categories_id',
    );

    public function setListSearch(): void {
        $this->list_where = " fwentities_id IS NULL "; //only show uploads directly from user (not linked to specific entity)

        parent::setListSearch();

        //other filters add to $this->list_where here
        if (isset($this->list_filter['att_categories_id'])) {
            $this->list_where .= '  and att_categories_id=' . dbqi($this->list_filter['att_categories_id']);
        }
    }

    public function getListRows(): void {
        parent::getListRows(); // TODO: Change the autogenerated stub

        $AttCat = AttCategories::i();
        foreach ($this->list_rows as $k => &$row) {
            $row['field']       = 'value';
            $row['cat']         = $AttCat->one($row['att_categories_id']);
            $row['url_direct']  = $this->model->getUrlDirect($row);
            $row['url_s']       = $this->model->getUrlDirect($row, 's');
            $row['fsize_human'] = Utils::bytes2str($row['fsize']);
        }
        unset($row);

    }

    public function IndexAction(): ?array {
        $ps = parent::IndexAction();

        $ps['select_att_categories_ids'] = AttCategories::i()->listSelectOptions();
        return $ps;
    }

    public function ShowFormAction($form_id): ?array {
        $ps   = parent::ShowFormAction($form_id);
        $item = $ps['i'];

        $ps['fsize_human'] = Utils::bytes2str($item['fsize']);
        $ps['url']         = $this->model->getUrl($form_id);
        $ps['url_m']       = ($item['is_image'] ? $this->model->getUrl($form_id, 'm') : '');

        $ps['select_options_att_categories_id'] = AttCategories::i()->listSelectOptions();

        return $ps;
    }

    public function SaveAction($form_id): ?array {
        $this->route_onerror = FW::ACTION_SHOW_FORM; //set route to go if error happens

        $id     = intval($form_id);
        $item   = reqh('item');
        $is_new = $id == 0;
        $files  = UploadUtils::getPostedFiles('file1');

        $this->Validate($id, $item, $files);
        #load old record if necessary
        #$item_old = $this->model->one($id);

        $itemdb = FormUtils::filter($item, $this->save_fields);
        if (!strlen($itemdb["iname"])) {
            $itemdb["iname"] = 'new file upload';
        }

        if ($id) {
            $this->model->update($id, $itemdb);
            $this->fw->flash("updated", 1);

            // Proceed upload - for edit - just one file
            $this->model->uploadOne($id, $files[0] ?? [], false);
        } else {
            $addedAtt = $this->model->uploadMulti($itemdb);
            if ($addedAtt) {
                $id = (int)$addedAtt[0]['id'];
            }
            $this->fw->flash("added", 1);
        }

        $ps['id'] = $id;
        if ($id > 0) {
            $item           = $this->model->one($id);
            $ps['success']  = true;
            $ps['url']      = $this->model->getUrlDirect($item);
            $ps['iname']    = $item['iname'];
            $ps['is_image'] = $item['is_image'];
        } else {
            $ps['success'] = false;
        }

        $this->fw->flash("success", "File uploaded");

        return $this->afterSave(true, $id, $is_new, FW::ACTION_SHOW_FORM, '', $ps);
    }

    public function Validate($id, $item, $files = array()): void {
        #only require file during first upload
        #only require iname during update
        $result   = true;
        $item_old = array();
        if ($id > 0) {
            $item_old = $this->model->one($id);
            $result   = $result && $this->validateRequired($item, $this->required_fields);
        } else {
            if (!count($files) || !$files[0]['size']) {
                $result = false;
                $this->setError('file1', 'NOFILE');
            }
        }

        $this->validateCheckResult($result);
    }

    public function SelectAction(): array {
        $category_icode    = reqs("category");
        $att_categories_id = reqi("att_categories_id");
        $AttCat            = AttCategories::i();

        if ($category_icode > '') {
            $att_cat = $AttCat->oneByIcode($category_icode);
            if (count($att_cat)) {
                $att_categories_id = $att_cat['id'];
            }
        }

        $rows = $this->model->listByCategory($att_categories_id);
        foreach ($rows as $key => $row) {
            $row['direct_url'] = $this->model->getUrlDirect($row);
        }

        $ps = array(
            'att_dr'                   => $rows,
            'select_att_categories_id' => $AttCat->listSelectOptions(),
            'att_categories_id'        => $att_categories_id,
        );
        return $ps;
    }
}//end of class
