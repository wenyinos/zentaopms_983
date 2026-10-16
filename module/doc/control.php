<?php
/**
 * The control file of doc module of ZenTaoPMS.
 *
 * @copyright   Copyright 2009-2015 青岛易软天创网络科技有限公司(QingDao Nature Easy Soft Network Technology Co,LTD, www.cnezsoft.com)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @author      Chunsheng Wang <chunsheng@cnezsoft.com>
 * @package     doc
 * @version     $Id: control.php 933 2010-07-06 06:53:40Z wwccss $
 * @link        http://www.zentao.net
 */
class doc extends control
{
    /**
     * Construct function, load user, tree, action auto.
     *
     * @access public
     * @return void
     */
    public function __construct($moduleName = '', $methodName = '')
    {
        parent::__construct($moduleName, $methodName);
        $this->loadModel('user');
        $this->loadModel('tree');
        $this->loadModel('action');
        $this->loadModel('product');
        $this->loadModel('project');
        $this->from      = $this->cookie->from ? $this->cookie->from : 'doc';
        $this->productID = $this->cookie->product ? $this->cookie->product : '0';
    }

    /**
     * Go to browse page.
     *
     * @access public
     * @return void
     */
    public function index()
    {
        $this->doc->setMenu();

        $this->app->loadClass('pager', $static = true);
        $pager = new pager(0, 5, 1);

        $this->view->title            = $this->lang->doc->common . $this->lang->colon . $this->lang->doc->index;
        $this->view->position[]       = $this->lang->doc->index;
        $this->view->latestEditedDocs = $this->loadModel('doc')->getDocsByBrowseType(0, 'byediteddate', 0, 0, 'editedDate_desc, id_desc', $pager);
        $this->view->myDocs           = $this->loadModel('doc')->getDocsByBrowseType(0, 'openedbyme', 0, 0, 'addedDate_desc', $pager);
        $this->view->statisticInfo    = $this->doc->getStatisticInfo();
        $this->view->users            = $this->loadModel('user')->getPairs('noletter');
        $this->view->doingProjects    = $this->loadModel('project')->getList('isdoing', 5);

        $this->display();
    }

    /**
     * Browse docs.
     *
     * @param  string|int $libID    product|project or the int id of custom library
     * @param  int    $moduleID
     * @param  int    $productID
     * @param  int    $projectID
     * @param  string $orderBy
     * @param  int    $recTotal
     * @param  int    $recPerPage
     * @param  int    $pageID
     * @access public
     * @return void
     */
    public function browse($libID = 0, $browseType = 'all', $param = 0, $orderBy = 'id_desc', $from = 'doc', $recTotal = 0, $recPerPage = 20, $pageID = 1)
    {
        $this->from = $from;
        setcookie('from',  $from, $this->config->cookieLife, $this->config->webRoot);

        $this->loadModel('search');

        /* Set browseType.*/
        $browseType = strtolower($browseType);
        $queryID    = ($browseType == 'bysearch') ? (int)$param : 0;
        $moduleID   = ($browseType == 'bymodule') ? (int)$param : 0;

        $type = '';
        $productID = 0;
        $projectID = 0;
        if($libID)
        {
            $type = 'custom';
            $lib  = $this->doc->getLibByID($libID);
            if($lib->product or $lib->project)
            {
                $type = $lib->product ? 'product' : 'project';
                $productID = $lib->product;
                $projectID = $lib->project;
            }
        }

        $this->libs = $this->doc->getLibs($type);

        /* According the from, set menus. */
        if($from == 'product')
        {
            $this->lang->doc->menu      = $this->lang->product->menu;
            $this->lang->doc->menuOrder = $this->lang->product->menuOrder;
            $this->product->setMenu($this->product->getPairs(), $lib->product);
            $this->lang->set('menugroup.doc', 'product');

            if(common::hasPriv('doc', 'create')) $this->lang->modulePageActions = html::a(helper::createLink('doc', 'create', "libID=$libID"), "<i class='icon icon-plus'></i> " . $this->lang->doc->create, '', "class='btn btn-primary'");
        }
        elseif($from == 'project')
        {
            $this->lang->doc->menu      = $this->lang->project->menu;
            $this->lang->doc->menuOrder = $this->lang->project->menuOrder;
            $this->project->setMenu($this->project->getPairs('nocode'), $lib->project);
            $this->lang->set('menugroup.doc', 'project');

            if(common::hasPriv('doc', 'create')) $this->lang->modulePageActions = html::a(helper::createLink('doc', 'create', "libID=$libID"), "<i class='icon icon-plus'></i> " . $this->lang->doc->create, '', "class='btn btn-primary'");
        }
        else
        {
            $menuType = (!$type && in_array($browseType, array_keys($this->lang->doc->fastMenuList))) ? $browseType : $type;
            $this->doc->setMenu($menuType, $libID, $moduleID, $productID, $projectID);
        }
        $this->session->set('docList', $this->app->getURI(true));

        /* Set header and position. */
        $this->view->title      = $this->lang->doc->common . ($libID ? $this->lang->colon . $this->libs[$libID] : '');
        $this->view->position[] = $libID ? $this->libs[$libID] : '';

        /* Load pager. */
        $this->app->loadClass('pager', $static = true);
        $pager = new pager($recTotal, $recPerPage, $pageID);

        /* Append id for secend sort. */
        $sort = $this->loadModel('common')->appendOrder($orderBy);

        /* Build the search form. */
        $actionURL = $this->createLink('doc', 'browse', "lib=$libID&browseType=bySearch&queryID=myQueryID&orderBy=$orderBy&from=$from");
        $this->doc->buildSearchForm($libID, $this->libs, $queryID, $actionURL, $type);

        $title  = '';
        $module = $moduleID ? $this->loadModel('tree')->getByID($moduleID) : '';
        if($module) $title = $module->name;
        if($libID)  $title = $this->libs[$libID];
        if(in_array($browseType, array_keys($this->lang->doc->fastMenuList))) $title = $this->lang->doc->fastMenuList[$browseType];
        if($browseType == 'fastsearch')
        {
            if($this->post->searchDoc) $this->session->set('searchDoc', $this->post->searchDoc);
            $title = '"' . $this->session->searchDoc  . '" ' . $this->lang->doc->searchResult;
        }
        else
        {
            $this->session->set('searchDoc', '');
        }

        $libs = array();
        if($browseType == 'collectedbyme')
        {
            $libs = $this->doc->getAllLibsByType('collector');
            $this->view->itemCounts = $this->doc->statLibCounts(array_keys($libs));
        }

        $this->view->title      = $title;
        $this->view->libID      = $libID;
        $this->view->moduleID   = $moduleID;
        $this->view->modules    = $this->doc->getDocMenu($libID, $moduleID, $orderBy == 'title_asc' ? 'name_asc' : 'id_desc', $browseType);
        $this->view->docs       = $this->doc->getDocsByBrowseType($libID, $browseType, $queryID, $moduleID, $sort, $pager);
        $this->view->users      = $this->loadModel('user')->getPairs('noletter');
        $this->view->orderBy    = $orderBy;
        $this->view->browseType = $browseType;
        $this->view->param      = $param;
        $this->view->type       = $type;
        $this->view->from       = $from;
        $this->view->pager      = $pager;
        $this->view->libs       = $libs;

        $this->display();
    }

    /**
     * Create a library.
     *
     * @param  string $type
     * @param  int    $objectID
     * @access public
     * @return void
     */
    public function createLib($type = '', $objectID = 0)
    {
        if(!empty($_POST))
        {
            $libID = $this->doc->createLib();
            if(!dao::isError())
            {
                $this->loadModel('action')->create('docLib', $libID, 'Created');
                die(js::locate($this->createLink($this->moduleName, 'browse', "libID=$libID"), 'parent.parent'));
            }
            else
            {
                echo js::error(dao::getError());
            }
        }
        $libTypeList = $this->lang->doc->libTypeList;
        $products    = $this->product->getPairs('nocode');
        $projects    = $this->project->getPairs('nocode');
        if(empty($products)) unset($libTypeList['product']);
        if(empty($projects)) unset($libTypeList['project']);

        $this->view->groups      = $this->loadModel('group')->getPairs();
        $this->view->users       = $this->user->getPairs('nocode');
        $this->view->products    = $products;
        $this->view->projects    = $projects;
        $this->view->type        = $type;
        $this->view->libTypeList = $libTypeList;
        $this->view->objectID    = $objectID;
        die($this->display());
    }

    /**
     * Edit a library.
     *
     * @param  int    $libID
     * @access public
     * @return void
     */
    public function editLib($libID)
    {
        if(!empty($_POST))
        {
            $changes = $this->doc->updateLib($libID);
            if(dao::isError()) die(js::error(dao::getError()));
            if($changes)
            {
                $actionID = $this->loadModel('action')->create('docLib', $libID, 'edited');
                $this->action->logHistory($actionID, $changes);
            }
            die(js::locate($this->createLink($this->moduleName, 'browse', "libID=$libID"), 'parent.parent'));
        }

        $lib = $this->doc->getLibByID($libID);
        if(!empty($lib->product)) $this->view->product = $this->dao->select('id,name')->from(TABLE_PRODUCT)->where('id')->eq($lib->product)->fetch();
        if(!empty($lib->project)) $this->view->project = $this->dao->select('id,name')->from(TABLE_PROJECT)->where('id')->eq($lib->project)->fetch();
        $this->view->lib     = $lib;
        $this->view->groups  = $this->loadModel('group')->getPairs();
        $this->view->users   = $this->user->getPairs('noletter', $lib->users);
        $this->view->libID   = $libID;

        die($this->display());
    }

    /**
     * Delete a library.
     *
     * @param  int    $libID
     * @param  string $confirm  yes|no
     * @access public
     * @return void
     */
    public function deleteLib($libID, $confirm = 'no')
    {
        if($libID == 'product' or $libID == 'project') die();
        if($confirm == 'no')
        {
            die(js::confirm($this->lang->doc->confirmDeleteLib, $this->createLink('doc', 'deleteLib', "libID=$libID&confirm=yes")));
        }
        else
        {
            $lib = $this->doc->getLibByID($libID);
            if(!empty($lib->main)) die(js::alert($this->lang->doc->errorMainSysLib));

            $this->doc->delete(TABLE_DOCLIB, $libID);
            die(js::locate($this->createLink('doc', 'browse'), 'parent'));
        }
    }

    /**
     * Create a doc.
     *
     * @param  int|string   $libID
     * @param  int          $moduleID
     * @param  int          $productID
     * @param  int          $projectID
     * @param  string       $from
     * @access public
     * @return void
     */
    public function create($libID, $moduleID = 0, $templateID = 0)
    {
        if(!empty($_POST))
        {
            $docResult = $this->doc->create();
            if(!$docResult or dao::isError()) $this->send(array('result' => 'fail', 'message' => dao::getError()));

            $docID = $docResult['id'];
            $lib   = $this->doc->getLibByID($this->post->lib);
            if($docResult['status'] == 'exists')
            {
                $this->send(array('result' => 'fail', 'message' => sprintf($this->lang->duplicate, $this->lang->doc->common), 'locate' => $this->createLink('doc', 'view', "docID=$docID")));
            }
            $this->action->create('doc', $docID, 'Created');

            $vars = "libID=$libID&browseType=byModule&moduleID={$this->post->module}&orderBy=id_desc&from=$this->from";
            $link = $this->createLink('doc', 'browse', $vars);
            $this->send(array('result' => 'success', 'message' => $this->lang->saveSuccess, 'locate' => $link));
        }

        $lib  = $this->doc->getLibByID($libID);
        $type = $lib->product ? 'product' : ($lib->project ? 'project' : 'custom');

        /* According the from, set menus. */
        if($this->from == 'product')
        {
            $this->lang->doc->menu      = $this->lang->product->menu;
            $this->lang->doc->menuOrder = $this->lang->product->menuOrder;
            $this->product->setMenu($this->product->getPairs(), $lib->product);
            $this->lang->set('menugroup.doc', 'product');

            if(common::hasPriv('doc', 'create')) $this->lang->modulePageActions = html::a(helper::createLink('doc', 'create', "libID=$libID"), "<i class='icon icon-plus'></i> " . $this->lang->doc->create, '', "class='btn btn-primary'");
        }
        elseif($this->from == 'project')
        {
            $this->lang->doc->menu      = $this->lang->project->menu;
            $this->lang->doc->menuOrder = $this->lang->project->menuOrder;
            $this->project->setMenu($this->project->getPairs('nocode'), $lib->project);
            $this->lang->set('menugroup.doc', 'project');

            if(common::hasPriv('doc', 'create')) $this->lang->modulePageActions = html::a(helper::createLink('doc', 'create', "libID=$libID"), "<i class='icon icon-plus'></i> " . $this->lang->doc->create, '', "class='btn btn-primary'");
        }
        else
        {
            $this->doc->setMenu($type, $libID, $moduleID, $lib->product, $lib->project);
        }

        $this->view->title      = $lib->name . $this->lang->colon . $this->lang->doc->create;
        $this->view->position[] = html::a($this->createLink('doc', 'browse', "libID=$libID"), $lib->name);
        $this->view->position[] = $this->lang->doc->create;

        $this->view->libID            = $libID;
        $this->view->moduleOptionMenu = $this->tree->getOptionMenu($libID, 'doc', $startModuleID = 0);
        $this->view->moduleID         = $moduleID;
        $this->view->type             = $type;
        $this->view->groups           = $this->loadModel('group')->getPairs();
        $this->view->users            = $this->user->getPairs('nocode');
        $this->view->template         = $templateID ? $this->doc->getById($templateID, 0, true) : false;

        $this->display();
    }

    /**
     * Edit a doc.
     *
     * @param  int    $docID
     * @access public
     * @return void
     */
    public function edit($docID, $comment = false)
    {
        if(!empty($_POST))
        {
            if($comment == false)
            {
                $result = $this->doc->update($docID);
                if(dao::isError()) $this->send(array('result' => 'fail', 'message' => dao::getError()));
                $changes = $result['changes'];
                $files   = $result['files'];
            }
            if($this->post->comment != '' or !empty($changes) or !empty($files))
            {
                $action = !empty($changes) ? 'Edited' : 'Commented';
                $fileAction = '';
                if(!empty($files)) $fileAction = $this->lang->addFiles . join(',', $files) . "\n" ;
                $actionID = $this->action->create('doc', $docID, $action, $fileAction . $this->post->comment);
                if(!empty($changes)) $this->action->logHistory($actionID, $changes);
            }
            $this->send(array('result' => 'success', 'message' => $this->lang->saveSuccess, 'locate' => $this->createLink('doc', 'view', "docID=$docID")));
        }

        /* Get doc and set menu. */
        $doc = $this->doc->getById($docID);
        $libID = $doc->lib;

        if($doc->contentType == 'markdown') $this->config->doc->markdown->edit = array('id' => 'content', 'tools' => 'toolbar');

        $lib        = $this->doc->getLibByID($libID);
        $type       = $lib->product ? 'product' : ($lib->project ? 'project' : 'custom');
        $this->doc->setMenu($type, $libID, $doc->module, $lib->product, $lib->project);

        $this->view->title      = $lib->name . $this->lang->colon . $this->lang->doc->edit;
        $this->view->position[] = html::a($this->createLink('doc', 'browse', "libID=$libID"), $lib->name);
        $this->view->position[] = $this->lang->doc->edit;

        $this->view->doc              = $doc;
        $this->view->moduleOptionMenu = $this->tree->getOptionMenu($libID, 'doc', $startModuleID = 0);
        $this->view->type             = $type;
        $this->view->groups           = $this->loadModel('group')->getPairs();
        $this->view->users            = $this->user->getPairs('noletter', $doc->users);
        $this->display();
    }

    /**
     * View a doc.
     *
     * @param  int    $docID
     * @access public
     * @return void
     */
    public function view($docID, $version = 0)
    {
        /* Get doc. */
        $doc = $this->doc->getById($docID, $version, true);
        if(!$doc) die(js::error($this->lang->notFound) . js::locate('back'));

        if($doc->contentType == 'markdown')
        {
            $hyperdown    = $this->app->loadClass('hyperdown');
            $doc->content = $hyperdown->makeHtml($doc->content);
            $doc->digest  = $hyperdown->makeHtml($doc->digest);
        }

        /* Check priv when lib is product or project. */
        $lib  = $this->doc->getLibByID($doc->lib);
        $type = $lib->product ? 'product' : ($lib->project ? 'project' : 'custom');

        /* Set menu. */
        $this->doc->setMenu($type, $doc->lib, $doc->module, $lib->product, $lib->project);

        $this->view->title      = "DOC #$doc->id $doc->title - " . $lib->name;
        $this->view->position[] = html::a($this->createLink('doc', 'browse', "libID=$doc->lib"), $lib->name);
        $this->view->position[] = $this->lang->doc->view;

        $this->view->doc        = $doc;
        $this->view->lib        = $lib;
        $this->view->type       = $type;
        $this->view->version    = $version ? $version : $doc->version;
        $this->view->actions    = $this->loadModel('action')->getList('doc', $docID);
        $this->view->users      = $this->user->getPairs('noclosed,noletter');
        $this->view->preAndNext = $this->loadModel('common')->getPreAndNextObject('doc', $docID);
        $this->view->keTableCSS = $this->doc->extractKETableCSS($doc->content);

        /* 记录浏览动作：供「最近浏览」取数，并让 views 自增（4 秒内重复不重复记）。 */
        $this->doc->createAction($docID, 'view');

        $this->display();
    }

    /**
     * Delete a doc.
     *
     * @param  int    $docID
     * @param  string $confirm  yes|no
     * @access public
     * @return void
     */
    public function delete($docID, $confirm = 'no')
    {
        if($confirm == 'no')
        {
            die(js::confirm($this->lang->doc->confirmDelete, inlink('delete', "docID=$docID&confirm=yes")));
        }
        else
        {
            $this->doc->delete(TABLE_DOC, $docID);

            /* if ajax request, send result. */
            if($this->server->ajax)
            {
                if(dao::isError())
                {
                    $response['result']  = 'fail';
                    $response['message'] = dao::getError();
                }
                else
                {
                    $response['result']  = 'success';
                    $response['message'] = '';
                }
                $this->send($response);
            }
            die(js::locate($this->session->docList, 'parent'));
        }
    }

    /**
     * Collect doc, doclib or module of doclib.
     *
     * @param  int    $objectID
     * @param  int    $objectType
     * @access public
     * @return void
     */
    public function collect($objectID, $objectType)
    {
        if($objectType == 'doc')    $table = TABLE_DOC;
        if($objectType == 'doclib') $table = TABLE_DOCLIB;
        if($objectType == 'module') $table = TABLE_MODULE;
        $collectors = $this->dao->select('collector')->from($table)->where('id')->eq($objectID)->fetch('collector');

        $hasCollect = strpos($collectors, ",{$this->app->user->account},") !== false;
        if($hasCollect)
        {
            $collectors = str_replace(",{$this->app->user->account},", ',', $collectors);
        }
        else
        {
            $collectors = explode(',', $collectors);
            $collectors[] = $this->app->user->account;
            $collectors = implode(',', $collectors);
        }

        $collectors = trim($collectors, ',') ? ',' . trim($collectors, ',') . ',' : '';

        $this->dao->update($table)->set('collector')->eq($collectors)->where('id')->eq($objectID)->exec();

        $this->send(array('status' => $hasCollect ? 'no' : 'yes'));
    }

    /**
     * Sort doc lib.
     *
     * @access public
     * @return void
     */
    public function sort()
    {
        if($_POST)
        {
            foreach($_POST as $id => $order)
            {
                $this->dao->update(TABLE_DOCLIB)->set('order')->eq($order)->where('id')->eq($id)->exec();
            }
            if(dao::isError()) $this->send(array('result' => 'fail', 'message' => dao::getError()));
            $this->send(array('result' => 'success'));
        }
    }

    /**
     * Ajax get modules by libID.
     *
     * @param  int    $libID
     * @access public
     * @return void
     */
    public function ajaxGetModules($libID)
    {
        $moduleOptionMenu = $this->tree->getOptionMenu($libID, 'doc', $startModuleID = 0);
        die(html::select('module', $moduleOptionMenu, 0, "class='form-control'"));
    }

    /**
     * Ajax fixed menu.
     *
     * @param  int    $libID
     * @param  string $type
     * @access public
     * @return void
     */
    public function ajaxFixedMenu($libID, $type = 'fixed')
    {
        $customMenuKey = $this->config->global->flow . '_doc';
        $customMenus = $this->loadModel('setting')->getItem("owner={$this->app->user->account}&module=common&section=customMenu&key={$customMenuKey}");
        if($customMenus) $customMenus = json_decode($customMenus);
        if(empty($customMenus))
        {
            if($type == 'remove') die(js::reload('parent'));
            $customMenus = array();
            $i = 0;
            foreach($this->lang->doc->menu as $name => $item)
            {
                if($name == 'list') continue;
                $customMenu = new stdclass();
                $customMenu->name = $name;
                $customMenu->order = $i;
                $customMenus[] = $customMenu;
                $i++;
            }
        }

        $customMenus = (array)$customMenus;
        foreach($customMenus as $i => $customMenu)
        {
            if(isset($customMenu->name) and $customMenu->name == "custom{$libID}") unset($customMenus[$i]);
        }

        $lib = $this->doc->getLibByID($libID);
        $customMenu = new stdclass();
        $customMenu->name      = "custom{$libID}";
        $customMenu->order     = count($customMenus);
        $customMenu->float     = 'right';
        if($type == 'fixed') $customMenus[] = $customMenu;
        $this->setting->setItem("{$this->app->user->account}.common.customMenu.{$customMenuKey}", json_encode($customMenus));
        die(js::reload('parent'));
    }

    /**
     * Ajax get all libs
     *
     * @access public
     * @return void
     */
    public function ajaxGetAllLibs()
    {
        die(json_encode($this->doc->getAllLibGroups()));
    }

    /**
     * Show all libs by type.
     *
     * @param  string $type
     * @param  string $product
     * @param  int    $recTotal
     * @param  int    $recPerPage
     * @param  int    $pageID
     * @access public
     * @return void
     */
    public function allLibs($type, $product = 0, $recTotal = 0, $recPerPage = 20, $pageID = 1)
    {
        setcookie('product', $product, $this->config->cookieLife, $this->config->webRoot);

        $libName = isset($this->lang->doc->systemLibs[$type]) ? $this->lang->doc->systemLibs[$type] : $this->lang->doc->customAB;
        $crumb   = html::a(inlink('allLibs', "type=$type&product=$product"), $libName);
        if($product and $type == 'project') $crumb = $this->doc->getProductCrumb($product);

        $this->view->title      = $libName;
        $this->view->position[] = $libName;

        $this->doc->setMenu($type, $libID = 0, $moduleID = 0, $productID = 0, $projectID = 0, $crumb);

        /* Load pager. */
        $this->app->loadClass('pager', $static = true);
        $pager = new pager($recTotal, $recPerPage, $pageID);

        $libs    = $this->doc->getAllLibsByType($type, $pager, $product);
        $subLibs = array();
        if($type == 'product' or $type == 'project')
        {
            $subLibs = $this->doc->getSubLibGroups($type, array_keys($libs));
            if($this->cookie->browseType == 'bylist') $this->view->users = $this->loadModel('user')->getPairs('noletter');
        }
        if($type == 'custom') $this->view->itemCounts = $this->doc->statLibCounts(array_keys($libs));

        $this->view->type    = $type;
        $this->view->libs    = $libs;
        $this->view->subLibs = $subLibs;
        $this->view->pager   = $pager;
        $this->view->product = $product;
        $this->display();
    }

    /**
     * Show files for product or project.
     *
     * @param  int    $type
     * @param  int    $objectID
     * @access public
     * @return void
     */
    public function showFiles($type, $objectID, $viewType = '', $orderBy = 't1.id_desc', $recTotal = 0, $recPerPage = 20, $pageID = 1)
    {
        $uri = $this->app->getURI(true);
        $this->app->session->set('taskList',  $uri);
        $this->app->session->set('storyList', $uri);
        $this->app->session->set('docList',   $uri);

        if(empty($viewType)) $viewType = !empty($_COOKIE['docFilesViewType']) ? $this->cookie->docFilesViewType : 'card';
        setcookie('docFilesViewType', $viewType, $this->config->cookieLife, $this->config->webRoot);

        $table  = $type == 'product' ? TABLE_PRODUCT : TABLE_PROJECT;
        $object = $this->dao->select('id,name')->from($table)->where('id')->eq($objectID)->fetch();

        /* According the from, set menus. */
        if($this->from == 'product')
        {
            $this->lang->doc->menu      = $this->lang->product->menu;
            $this->lang->doc->menuOrder = $this->lang->product->menuOrder;
            $this->product->setMenu($this->product->getPairs(), $objectID);
            $this->lang->set('menugroup.doc', 'product');
        }
        elseif($this->from == 'project')
        {
            $this->lang->doc->menu      = $this->lang->project->menu;
            $this->lang->doc->menuOrder = $this->lang->project->menuOrder;
            $this->project->setMenu($this->project->getPairs('nocode'), $objectID);
            $this->lang->set('menugroup.doc', 'project');
        }
        else
        {
            $crumb  = html::a(inlink('allLibs', "type=$type"), $type == 'product' ? $this->lang->productCommon : $this->lang->projectCommon) . $this->lang->doc->separator;
            if($this->productID and $type == 'project') $crumb = $this->doc->getProductCrumb($this->productID, $objectID);
            $crumb .= html::a(inlink('objectLibs', "type=$type&objectID=$objectID"), $object->name);
            $crumb .= $this->lang->doc->separator . ' ' . $this->lang->doclib->files;

            $productID = 0;
            $projectID = 0;
            if($type == 'product')
            {
                $productID = $objectID;
                $lib = $this->product->getById($objectID);
                if(!$this->product->checkPriv($lib)) $this->accessDenied();
            }

            if($type == 'project')
            {
                $projectID = $objectID;
                $lib = $this->project->getById($objectID);
                if(!$this->project->checkPriv($lib)) $this->accessDenied();
            }

            $this->doc->setMenu($type, $libID = 0, $moduleID = 0, $productID, $projectID, $crumb);
        }

        /* Load pager. */
        $this->app->loadClass('pager', $static = true);
        $pager = new pager($recTotal, $recPerPage, $pageID);

        $this->view->title      = $object->name;
        $this->view->position[] = $object->name;

        $this->view->type       = $type;
        $this->view->object     = $object;
        $this->view->files      = $this->doc->getLibFiles($type, $objectID, $orderBy, $pager);
        $this->view->users      = $this->loadModel('user')->getPairs('noletter');
        $this->view->pager      = $pager;
        $this->view->viewType   = $viewType;
        $this->view->orderBy    = $orderBy;
        $this->view->objectID   = $objectID;

        $this->display();
    }

    /**
     * Show libs for product or project
     *
     * @access private
     * @return void
     */
    private function accessDenied()
    {
        echo(js::alert($this->lang->doc->accessDenied));

        $loginLink = $this->config->requestType == 'GET' ? "?{$this->config->moduleVar}=user&{$this->config->methodVar}=login" : "user{$this->config->requestFix}login";

       if(strpos($this->server->http_referer, $loginLink) !== false) die(js::locate(inlink('index')));
       die(js::locate('back'));
    }

    /**
     * Show libs for product or project
     *
     * @param  string $type
     * @param  int    $objectID
     * @param  string $from
     * @access public
     * @return void
     */
    public function objectLibs($type, $objectID, $from = 'doc')
    {
        $this->from = $from;
        setcookie('from', $from, $this->config->cookieLife, $this->config->webRoot);

        $table  = $type == 'product' ? TABLE_PRODUCT : TABLE_PROJECT;
        $object = $this->dao->select('id,name')->from($table)->where('id')->eq($objectID)->fetch();
        if(empty($object)) $this->locate($this->createLink($type, 'create'));
        if($from == 'product')
        {
            $this->lang->doc->menu      = $this->lang->product->menu;
            $this->lang->doc->menuOrder = $this->lang->product->menuOrder;
            $this->product->setMenu($this->product->getPairs(), $objectID);
            $this->lang->set('menugroup.doc', 'product');
        }
        elseif($from == 'project')
        {
            $this->lang->doc->menu      = $this->lang->project->menu;
            $this->lang->doc->menuOrder = $this->lang->project->menuOrder;
            $this->project->setMenu($this->project->getPairs('nocode'), $objectID);
            $this->lang->set('menugroup.doc', 'project');
        }
        else
        {
            $crumb  = html::a(inlink('allLibs', "type=$type"), $type == 'product' ? $this->lang->productCommon : $this->lang->projectCommon) . $this->lang->doc->separator;
            if($this->productID and $type == 'project') $crumb = $this->doc->getProductCrumb($this->productID, $objectID);
            $crumb .= html::a(inlink('objectLibs', "type=$type&objectID=$objectID"), $object->name);
            $productID = $type == 'product' ? $objectID : 0;
            $projectID = $type == 'project' ? $objectID : 0;
            $this->doc->setMenu($type, $libID = 0, $moduleID = 0, $productID, $projectID, $crumb);
        }

        /* Set Custom. */
        foreach(explode(',', $this->config->doc->customObjectLibs) as $libType) $customObjectLibs[$libType] = $this->lang->doc->customObjectLibs[$libType];
        $this->view->customObjectLibs = $customObjectLibs;
        $this->view->showLibs         = $this->config->doc->custom->objectLibs;

        $this->view->title      = $object->name;
        $this->view->position[] = $object->name;

        $this->view->type   = $type;
        $this->view->object = $object;
        $this->view->from   = $from;
        $this->view->libs   = $this->doc->getLibsByObject($type, $objectID);
        $this->display();
    }

    /**
     * Sort the doc categories of one lib.
     *
     * 目录复用 zt_module，排序直接转调 tree::updateOrder（它会按 parent/grade 归一为 10 的倍数）。
     *
     * @access public
     * @return void
     */
    public function sortCatalog()
    {
        if($_POST)
        {
            $orders = json_decode($this->post->orders, true);
            if(!is_array($orders)) $orders = array();

            $data = array();
            foreach($orders as $moduleID => $order)
            {
                if(!is_numeric($moduleID) or !is_numeric($order)) continue;
                $data[$moduleID] = $order;
            }
            if($data) $this->loadModel('tree')->updateOrder($data);
            if(dao::isError()) $this->send(array('result' => 'fail', 'message' => dao::getError()));
            $this->send(array('result' => 'success'));
        }
    }

    /**
     * Sort the docs of one lib.
     *
     * @access public
     * @return void
     */
    public function sortDoc()
    {
        if($_POST)
        {
            $orders = json_decode($this->post->orders, true);
            if(!is_array($orders)) $orders = array();

            $this->doc->updateDocOrder($orders);
            if(dao::isError()) $this->send(array('result' => 'fail', 'message' => dao::getError()));
            $this->send(array('result' => 'success'));
        }
    }

    /**
     * Move a doc to another lib / module.
     *
     * @param  int    $docID
     * @param  int    $libID
     * @access public
     * @return void
     */
    public function moveDoc($docID, $libID = 0)
    {
        if($_POST)
        {
            $data = fixer::input('post')->setDefault('module', 0)->get();
            if($this->doc->moveDoc($docID, $data))
            {
                $this->loadModel('action')->create('doc', $docID, 'Moved', '');
                $this->send(array('result' => 'success', 'message' => $this->lang->saveSuccess, 'closeModal' => true, 'load' => true));
            }
            $this->send(array('result' => 'fail', 'message' => dao::getError()));
        }

        $this->view->doc   = $this->doc->getById($docID);
        $this->view->libID = $libID;
        $this->view->libs  = $this->doc->getLibs('all');
        $this->display();
    }

    /**
     * Copy a doc to another lib / module.
     *
     * @param  int    $docID
     * @param  int    $libID
     * @access public
     * @return void
     */
    public function copyDoc($docID, $libID = 0)
    {
        if($_POST)
        {
            $data = fixer::input('post')->setDefault('module', 0)->get();
            $newID = $this->doc->copyDoc($docID, $data);
            if($newID)
            {
                $this->loadModel('action')->create('doc', $newID, 'Copied', '');
                $this->send(array('result' => 'success', 'message' => $this->lang->saveSuccess, 'closeModal' => true, 'load' => true));
            }
            $this->send(array('result' => 'fail', 'message' => dao::getError()));
        }

        $this->view->doc   = $this->doc->getById($docID);
        $this->view->libID = $libID;
        $this->view->libs  = $this->doc->getLibs('all');
        $this->display();
    }

    /**
     * Batch move docs.
     *
     * @param  string $encodeDocIdList
     * @param  int    $libID
     * @param  int    $moduleID
     * @access public
     * @return void
     */
    public function batchMoveDoc($encodeDocIdList, $libID = 0, $moduleID = 0)
    {
        if($_POST)
        {
            $data = fixer::input('post')->setDefault('module', 0)->get();
            $docIdList = json_decode(base64_decode($encodeDocIdList), true);
            if(!is_array($docIdList)) $docIdList = array();

            $moved = 0;
            foreach($docIdList as $docID)
            {
                if(!is_numeric($docID)) continue;
                if($this->doc->moveDoc($docID, $data))
                {
                    $this->loadModel('action')->create('doc', $docID, 'Moved', '');
                    $moved++;
                }
            }
            if($moved) $this->send(array('result' => 'success', 'message' => sprintf($this->lang->doc->batchMoveResult, $moved), 'closeModal' => true, 'load' => true));
            $this->send(array('result' => 'fail', 'message' => array('batchMove' => array($this->lang->doc->batchMoveFailed))));
        }

        $this->view->docIdList = base64_decode($encodeDocIdList);
        $this->view->libID     = $libID;
        $this->view->moduleID  = $moduleID;
        $this->view->libs      = $this->doc->getLibs('all');
        $this->display();
    }

    /**
     * Browse the doc templates.
     *
     * 模板挂在 type='template' 的 doclib 上，分类复用 zt_module（type='docTemplate'）。
     * 带 libID 进来时每行多一个「用此模板新建文档」，落到该库的 create。
     *
     * @param  int    $libID       目标库（用于「用此模板新建」）
     * @param  int    $moduleID    目标目录
     * @param  int    $typeModule  按分类过滤
     * @param  string $orderBy
     * @param  int    $recTotal
     * @param  int    $recPerPage
     * @param  int    $pageID
     * @access public
     * @return void
     */
    public function browseTemplate($libID = 0, $moduleID = 0, $typeModule = 0, $orderBy = 'id_desc', $recTotal = 0, $recPerPage = 20, $pageID = 1)
    {
        $scope = $this->doc->getTemplateLibID();
        if(!$scope) die(js::error($this->lang->doc->error->noTemplateLib) . js::locate('back'));

        $this->app->loadClass('pager', $static = true);
        $pager = new pager($recTotal, $recPerPage, $pageID);

        $templates = $this->doc->getDocTemplateList($scope, $orderBy, $pager);

        /* 分类树：把模板按 module 归组，视图里渲染成侧栏。 */
        $modules = $this->doc->getTemplateModules();
        $grouped = array();
        foreach($templates as $template)
        {
            $key = $template->module ? $template->module : 0;
            if($typeModule and $key != $typeModule) continue;
            $grouped[$key][$template->id] = $template;
        }

        $this->doc->setMenu('custom', 0, 0, 0, 0);

        $this->view->title      = $this->lang->doc->browseTemplate;
        $this->view->position[] = $this->lang->doc->browseTemplate;
        $this->view->scope      = $scope;
        $this->view->templates  = $templates;
        $this->view->grouped    = $grouped;
        $this->view->modules    = $modules;
        $this->view->typeModule = $typeModule;
        $this->view->libID      = $libID;
        $this->view->moduleID   = $moduleID;
        $this->view->orderBy    = $orderBy;
        $this->view->pager      = $pager;
        $this->view->users       = $this->loadModel('user')->getPairs('noletter');
        $this->view->modulePairs = $this->getTemplateModulePairs();
        $this->display();
    }

    /**
     * Create a template.
     *
     * @param  int    $moduleID
     * @access public
     * @return void
     */
    public function createTemplate($moduleID = 0)
    {
        if($_POST)
        {
            $data = fixer::input('post')->setDefault('keywords', '')->get();
            $templateID = $this->doc->createTemplate($data);
            if(!$templateID) $this->send(array('result' => 'fail', 'message' => dao::getError()));
            $this->send(array('result' => 'success', 'message' => $this->lang->saveSuccess, 'closeModal' => true, 'load' => true));
        }

        $this->view->title     = $this->lang->doc->createTemplate;
        $this->view->template  = false;
        $this->view->moduleID  = $moduleID;
        $this->view->modules   = $this->getTemplateModulePairs();
        $this->display();
    }

    /**
     * Edit a template.
     *
     * @param  int    $templateID
     * @access public
     * @return void
     */
    public function editTemplate($templateID)
    {
        if($_POST)
        {
            $data = fixer::input('post')->setDefault('keywords', '')->get();
            $this->doc->updateTemplate($templateID, $data);
            if(dao::isError()) $this->send(array('result' => 'fail', 'message' => dao::getError()));
            $this->send(array('result' => 'success', 'message' => $this->lang->saveSuccess, 'closeModal' => true, 'load' => true));
        }

        $template = $this->doc->getById($templateID, 0, true);
        if(!$template) die(js::error($this->lang->notFound) . js::locate('back'));

        $this->view->title    = $this->lang->doc->editTemplate;
        $this->view->template = $template;
        $this->view->moduleID = $template->module;
        $this->view->modules  = $this->getTemplateModulePairs();
        $this->display('doc', 'createtemplate');
    }

    /**
     * Delete a template.
     *
     * @param  int    $templateID
     * @param  string $confirm
     * @access public
     * @return void
     */
    public function deleteTemplate($templateID, $confirm = 'no')
    {
        if($confirm == 'no') die(js::confirm($this->lang->doc->confirmDeleteTemplate, inlink('deleteTemplate', "templateID=$templateID&confirm=yes")));

        $this->doc->deleteTemplate($templateID);
        die(js::reload('parent'));
    }

    /**
     * Add a template category.
     *
     * @param  int    $scope
     * @param  int    $parentModule
     * @access public
     * @return void
     */
    public function addTemplateType($scope = 0, $parentModule = 0)
    {
        if($_POST)
        {
            $data = fixer::input('post')->setDefault('parent', 0)->get();
            if($this->doc->addTemplateType($data))
            {
                $this->send(array('result' => 'success', 'message' => $this->lang->saveSuccess, 'closeModal' => true, 'load' => true));
            }
            $this->send(array('result' => 'fail', 'message' => dao::getError()));
        }

        $this->view->parentModule = $parentModule;
        $this->view->title        = $this->lang->doc->addTemplateType;
        $this->display();
    }

    /**
     * Edit a template category.
     *
     * 分类就是 zt_module，直接复用 tree 模块的编辑弹窗。
     *
     * @param  int    $moduleID
     * @access public
     * @return void
     */
    public function editTemplateType($moduleID)
    {
        echo $this->fetch('tree', 'edit', "moduleID={$moduleID}&type=docTemplate");
    }

    /**
     * Delete a template category.
     *
     * @param  int    $moduleID
     * @param  string $confirm
     * @access public
     * @return void
     */
    public function deleteTemplateType($moduleID, $confirm = 'no')
    {
        $templates = $this->doc->getTemplatesByType($moduleID);
        if(!empty($templates)) die(js::alert($this->lang->doc->error->templateTypeNotEmpty));

        if($confirm == 'no') die(js::confirm($this->lang->doc->confirmDeleteType, inlink('deleteTemplateType', "moduleID=$moduleID&confirm=yes")));

        $this->dao->update(TABLE_MODULE)->set('deleted')->eq(1)->where('id')->eq($moduleID)->exec();
        die(js::reload('parent'));
    }

    /**
     * Template categories as id => name pairs.
     *
     * @access public
     * @return array
     */
    public function getTemplateModulePairs()
    {
        $pairs = array(0 => '/');
        foreach($this->doc->getTemplateModules() as $module) $pairs[$module->id] = $module->name;
        return $pairs;
    }
}
