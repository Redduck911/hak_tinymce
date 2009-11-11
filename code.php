<?php
if (@txpinterface == 'admin') {
    add_privs('article','1,2,3,4,5,6');
	add_privs('hak_tinymce_prefs', '1,2');
	add_privs('hak_tinymce_js','1,2,3,4,5,6');
	add_privs('hak_tinymce_compressor_js','1,2,3,4,5,6');
	add_privs('hak_txpimage','1,2,3,4,5,6');
	add_privs('hak_txpcatselect','1,2,3,4,5,6');
	

	register_callback("hak_tinymce::js_prep", "hak_tinymce_js");
	register_callback("hak_tinymce::compressor_js_prep", "hak_tinymce_compressor_js");
	register_callback("hak_txpimage", "hak_txpimage");
	register_callback("hak_txpcatselect", "hak_txpcatselect");
	
	register_tab('extensions', 'hak_tinymce_prefs', 'hak_tinymce');
	register_callback('hak_tinymce::prefs', 'hak_tinymce_prefs');
    register_callback('hak_tinymce::inject_toggle', 'article_ui', 'extend_col_1');
	register_callback("hak_tinymce::inject_js", "article_ui", "extend_col_1");
    register_callback('hak_tinymce::override_markup_selects', 'article_ui', 'markup');
    register_callback('hak_tinymce::track_markup_selection', 'article_ui', 'view');
}

class hak_tinymce {
    
    public static function inject_toggle($event, $step, $default, $context_data='') {

        extract(self::getPrefs());

        if (!self::show_toggle($context_data)) {
            return;
        }

        $msg = '';

        if ($enable_body || $enable_excerpt) {
            $msg = '<h3 class="plain lever"><a href="#hak_tinymce">'.self::mce_gTxt('hak_toggle_editor').'</a></h3>'.
                '<div id="hak_tinymce" class="toggle" style="display:none">'.
                '<p>';
            if ($enable_body && self::showCheckbox('body', $context_data)) {
                $msg .= '<input type="checkbox" value="body" id="hakToggle-body" name="hak_tinymceToggle[]" class="checkbox" style="width:auto" '.self::isToggleChecked('body', $context_data).'" />'.
                    '<label for="hakToggle-body">'.ucwords(gTxt('article')).'</label><br />';
            }
            if ($enable_excerpt && self::showCheckbox('excerpt',$context_data)) {
                $msg .= '<input type="checkbox" value="excerpt" id="hakToggle-excerpt" name="hak_tinymceToggle[]" class="checkbox" style="width:auto" '.self::isToggleChecked('excerpt', $context_data).' />'.
                    '<label for="hakToggle-excerpt">'.ucwords(gTxt('excerpt')).'</label><br />';
            }
            $msg .= '</p></div>';
        }

        return $msg;
    }
    
    public static function inject_js($event, $step, $default, $context_data='') {
        global $prefs;

        if (self::check_install()) {
            $hak_tinymce = self::getPrefs();
            extract($prefs);
            
            extract($context_data);
            
            /* if this is a new article we use the global use textile setting.  From txp_article */
            if ($step == 'create') {
                $textile_body = $use_textile;
                $textile_excerpt = $use_textile;
            }


            $hak_tinymce["script_path"] = ($hak_tinymce["use_compressor"]) ? hak_compressor_path($hak_tinymce["tinymce_path"]) : $hak_tinymce["tinymce_path"];
            $msg = "<script language='javascript' type='text/javascript' src='".$hak_tinymce["script_path"]."'></script>";
            if ($hak_tinymce["use_compressor"]) {
                $msg .= "<script language='javascript' type='text/javascript' src='index.php?event=hak_tinymce_compressor_js'></script>";
            }
            $msg .= "<script language='javascript' type='text/javascript' src='index.php?event=hak_tinymce_js'></script>";
            if (self::show_toggle($context_data)) {
                return $msg;
            }

        }
    }

    public static function override_markup_selects($event, $step, $default, $context_data='') {
        extract(self::getPrefs());

        $empty = ' ';
        if ($hide_textile_select) {
            return $empty;
        }

        return $default;
    }

    public static function track_markup_selection($event, $step, $default, $context_data) {
        
        $toggles = gps("hak_tinymceToggle");
        
        if (self::is_edit_screen() || empty($toggles)) {
            return $default;
        }
        
        foreach ($toggles as $toggle) {
            $default .= hInput("hak_tinymceToggle[]", $toggle);
        }

        return $default;


    }

    public static function js_prep() {
        header('Content-type: application/x-javascript');
        echo self::js();
        exit(0);
    }

    public static function compressor_js_prep() {
        header('Content-type: application/x-javascript');
        echo self::compressor_js();
        exit(0);
    }

    public static function prefs($event, $step) {
        pagetop('hak_tinymce '.gTxt('preferences'), ($step == 'update' ? gTxt('preferences_saved') : ''));
	
        if ($step == 'install') {
            // Install the preferences table.
            self::install();
        }
	
        if ($step == 'uninstall') {
            //remove table
            safe_query("DROP TABLE ".PFX."txp_hak_tinymce");
        }
	
        if ($step == 'update') {
            extract(doSlash(gpsa(array(
                                       'hak_show_toggle', 
                                       'hak_hide_on_textile_edit', 
                                       'hak_tinymce_path', 
                                       'hak_tinymce_body_init', 
                                       'hak_tinymce_excerpt_init', 
                                       'hak_tinymce_callbacks',
                                       'hak_hide_textile_select',
                                       'hak_enable_body',
                                       'hak_enable_excerpt',
                                       'hak_use_compressor',
                                       'hak_tinymce_compressor_init'
                                       ))));

            safe_update('txp_hak_tinymce', "pref_value = '$hak_show_toggle'", "pref_name = 'show_toggle'");
            safe_update('txp_hak_tinymce', "pref_value = '$hak_hide_on_textile_edit'", "pref_name = 'hide_on_textile_edit'");
            safe_update('txp_hak_tinymce', "pref_value = '$hak_tinymce_path'", "pref_name = 'tinymce_path'");
            safe_update('txp_hak_tinymce', "pref_value = '$hak_tinymce_body_init'", "pref_name = 'body_init'");
            safe_update('txp_hak_tinymce', "pref_value = '$hak_tinymce_excerpt_init'", "pref_name = 'excerpt_init'");
            safe_update('txp_hak_tinymce', "pref_value = '$hak_tinymce_callbacks'", "pref_name = 'callbacks'");
            safe_update('txp_hak_tinymce', "pref_value = '$hak_hide_textile_select'", "pref_name = 'hide_textile_select'");
            safe_update('txp_hak_tinymce', "pref_value = '$hak_enable_body'", "pref_name = 'enable_body'");
            safe_update('txp_hak_tinymce', "pref_value = '$hak_enable_excerpt'", "pref_name = 'enable_excerpt'");
            safe_update('txp_hak_tinymce', "pref_value = '$hak_use_compressor'", "pref_name = 'use_compressor'");
            safe_update('txp_hak_tinymce', "pref_value = '$hak_tinymce_compressor_init'", "pref_name = 'compressor_init'");
        }

        if (self::check_install()) {
            extract(self::getPrefs());
            echo n.t.'<div style="margin: auto; width:40%;">'.
                n.t.t.hed('hak_tinymce '.gTxt('Preferences'), '1').
                n.n.form(
                         n.eInput('hak_tinymce_prefs').
                         n.sInput('update').
                         n.fInput('submit', 'update', 'Update', 'smallerbox').
                         n.graf(self::mce_gTxt('hak_show_toggle').br.
                                n.yesnoRadio('hak_show_toggle',$show_toggle).br.
                                n.tag(tag(self::mce_gTxt('auto_disable'),"em"),"small")
                                ).
                         n.graf(self::mce_gTxt('hak_hide_on_textile_edit').br.
                                n.yesnoRadio('hak_hide_on_textile_edit',$hide_on_textile_edit)
                                ).
                         n.graf(self::mce_gTxt('hak_hide_textile_select').br.
                                n.yesnoRadio('hak_hide_textile_select',$hide_textile_select)
                                ).
                         n.graf(self::mce_gTxt('hak_tinymce_path').br.
                                n.finput('text','hak_tinymce_path',$tinymce_path,'','','',60,'','hak_tinymce_path').
                                self::file_exists($tinymce_path)
                                ).
                         n.graf(self::mce_gTxt('enable_body').br.
                                n.yesnoRadio('hak_enable_body',$enable_body)
                                ).
                         n.graf(self::mce_gTxt('hak_tinymce_body_init').br.
                                tag(tag("(".self::mce_gTxt('line_end').")","em"),"small").n.href(self::mce_gTxt('documentation'),"http://tinymce.moxiecode.com/documentation.php").br.
                                n.text_area('hak_tinymce_body_init',200, 400, $body_init)
                                ).
                         n.graf(self::mce_gTxt('enable_excerpt').br.
                                n.yesnoRadio('hak_enable_excerpt',$enable_excerpt)
                                ).
                         n.graf(self::mce_gTxt('hak_tinymce_excerpt_init').br.
                                tag(tag("(".self::mce_gTxt('line_end').")","em"),"small").n.href(self::mce_gTxt('documentation'),"http://tinymce.moxiecode.com/documentation.php").br.
                                n.text_area('hak_tinymce_excerpt_init',200,400,$excerpt_init)
                                ).
                         n.graf(self::mce_gTxt('hak_tinymce_callbacks').br.
                                n.text_area('hak_tinymce_callbacks',200,400,$callbacks)
                                ).
                         n.graf(self::mce_gTxt('use_compressor').br.
                                n.yesnoRadio('hak_use_compressor',$use_compressor).
                                self::file_exists(hak_compressor_path($tinymce_path), "compressor_not_found")
                                ).
                         n.graf(self::mce_gTxt('hak_tinymce_compressor_init').br.
                                tag(tag("(".self::mce_gTxt('compressor_line_end').")","em"),"small").
                                n.href(self::mce_gTxt('documentation'),"http://wiki.moxiecode.com/index.php/TinyMCE:Compressor/PHP").br.
                                n.text_area('hak_tinymce_compressor_init',200,400,$compressor_init)
                                ).
                         n.n.fInput('submit', 'update', 'Update', 'smallerbox')
                         ).
                '</div>';
			echo n.t.'<div style="margin: 60px auto 0; width:40%;">'.
				n.hed(self::mce_gTxt('uninstall'), '1').
				n.t.t.graf(self::mce_gTxt('uninstall_message')).
				n.n.form(
                         n.eInput('hak_tinymce_prefs').
                         n.sInput('uninstall').
                         n.n.fInput('submit', 'uninstall', 'Uninstall ', 'smallerbox'),"","confirm('".self::mce_gTxt('uninstall_confirm')."')"
                         ).
				'</div>';
        } else {
            echo n.t.'<div style="margin: auto; width:40%;">'.
                n.t.t.hed('hak_tinymce '.gTxt('Preferences'), '1').
                n.graf(self::mce_gTxt('install_message')).
                n.n.form(
                         n.eInput('hak_tinymce_prefs').
                         n.sInput('install').
                         n.n.fInput('submit', 'install', 'Install ', 'smallerbox')
                         ).
                '</div>';
        }
    }

    // Private functions
    private function compressor_js() {
        extract(self::getPrefs());
        
        $js = "tinyMCE_GZ.init({ \n";
        $js.= rtrim($compressor_init, ",");
        $js .="});";
        return $js;
    }

    private function js() {

        extract(self::getPrefs());
        $hu = hu;
        $js = <<<EOF
            
            var hak_tinymce = (function () {
                    
                    var settings = {
                    body:{
                        document_base_url:"$hu",
                        $body_init
                        mode: "none",
                        elements:"body"
                    },
                    excerpt: {
                        document_base_url:"$hu",
                        $excerpt_init
                        mode:"none",
                        elements: "excerpt"
                    }
                    };
                    tinyMCE.init(settings.body);
                    tinyMCE.init(settings.excerpt);
                    var textileMap = [2,0,1];
        
                    var addControl = function (opts) {
                        tinyMCE.settings = settings[opts.id];
                        tinyMCE.execCommand('mceAddControl', false, opts.id);
                        var checkbox = $('#hakToggle-' + opts.id);
                        checkbox.attr('checked', true);
                        var select = $('#markup-' + opts.id);
                        originalMarkupSelect[opts.id] = select.val();
                        select.val(0);
                    };

                    var removeControl = function (opts) {
                        tinyMCE.settings = settings[opts.id];
                        tinyMCE.execCommand('mceRemoveControl', false, opts.id);
                        var checkbox = $('#hakToggle-' + opts.id);
                        checkbox.attr('checked', false);
                        var select = $('#markup-' + opts.id);
                        select.val(originalMarkupSelect[opts.id]);
                    };
                    
                    var originalMarkupSelect = {};

                    return {
                    toggleEditor: function() {
                            var id = $(this).val();
                            var mceControl = tinyMCE.get(id);
                            if (!!mceControl) {
                                removeControl({id:id});
                            } else {
                                addControl({id:id});
                            }
                        },
                            addEditor:function(id) {
                            addControl({id:id});
                        }, 
                            removeEditor:function(id) {
                            removeControl({id:id});
                        } 
                    }
                    
                })();

        $(document).ready(function () {
                $("#hak_tinymce input:checkbox").click(hak_tinymce.toggleEditor);
                $("#hak_tinymce input:checked").each(function (i) {
                        hak_tinymce.addEditor($(this).val());
                    });
            });

EOF;
		return $js;
    }
   
    private function getPrefs() {
        global $mcePrefs;
        
        if (!$mcePrefs) {
            $r = safe_rows_start('pref_name, pref_value', 'txp_hak_tinymce','1=1');
            if ($r) {
                while ($a = nextRow($r)) {
                    $out[$a['pref_name']] = $a['pref_value']; 
                }
                $mcePrefs = $out;
                return $mcePrefs;
            }
        }
        return $mcePrefs;
    }
 
    //--support functions 
    private function is_edit_screen() {
        $views = gpsa(array('from_view', 'view', 'step'));

        extract($views);
        return ($step == 'edit' || $view == 'text' || empty($from_view));
    }

    private function isToggleChecked($toggle, $context) {
        global $prefs;
        
        $which_textile = "textile_".$toggle;
        $textile_setting = !is_numeric($context[$which_textile]) ? $prefs["use_textile"] : $context[$which_textile];

        $msg = '';
        if (self::wasToggleChecked($toggle) || $textile_setting == LEAVE_TEXT_UNTOUCHED) {
            $msg = 'checked="checked"';
        }
        return $msg;
    }
    
    private function wasToggleChecked($toggle) {
        
        $toggles_array = gps("hak_tinymceToggle");
        return empty($toggles_array) ? false : in_array($toggle, $toggles_array);
    }

    private function show_toggle($context_data) {
        global $mcePrefs;
        
        if (!$mcePrefs["show_toggle"]) {
            return false;
        }

        if (empty($context_data)) {
            return true;
        }

        return self::showCheckbox('body', $context_data) || self::showCheckbox('excerpt', $context_data);
    }

    private function showCheckbox($textarea, $context_data) {
        global $mcePrefs;

        if ($mcePrefs["hide_on_textile_edit"] && !empty($context_data["ID"])) {
            
            if (!empty($context_data['textile_'.$textarea]) && !empty($context_data[ucwords($textarea)]) && $context_data['textile_'.$textarea] != LEAVE_TEXT_UNTOUCHED) {
                return false;
            }
        }
        return true;
    }
    
    public function mce_gTxt($what) {
        global $language;
	
        $en_us = array(
                       'hak_show_toggle' => 'Show editor toggle:',
                       'hak_hide_on_textile_edit' => 'Hide editor toggle when editing articles created with textile or convert linebreaks: ',
                       'hak_tinymce_body_init' => 'Initialization for article body editor:',
                       'hak_tinymce_excerpt_init' => 'Initialization for article excerpt editor:',
                       'hak_tinymce_callbacks' => 'Callback functions:',
                       'hak_tinymce_compressor_init' => 'Initialization for Gzip compressor editor:',
                       'hak_tinymce_path' => 'Path to tiny_mce script (relative to your textpattern directory or document root):',
                       'file_not_found' => 'File not found in specified location.',
                       'compressor_not_found' => 'Compressor files not found with TinyMCE files.',
                       'line_end' => 'All lines should end with commas.',
                       'compressor_line_end' => 'The last line should not end with a comma.',
                       'install_message' => 'hak_tinymce is not yet properly initialized.  Use the button below to create the preferences table.',
                       'hak_toggle_editor' => 'Toggle Editor',
                       'uninstall' => 'Uninstall',
                       'uninstall_message' => 'Using the button below will remove the hak_tinymce preferences table.  You will still have to remove the actual TinyMCE installation.',
                       'uninstall_confirm' => 'Are you sure you want to delete the preferences table?',
                       'insert_thumb' => 'Insert Thumbnail',
                       'insert_image' => 'Insert Full Size Image',
                       'hak_hide_textile_select' => 'Hide "Use textile" Dropdowns:',
                       'enable_body' => 'Enable editor for article body:',
                       'enable_excerpt' => 'Enable editor for article excerpt:',
                       'auto_disable' => 'The toggle is automatically hidden if you disable the editor for the article body and the article excerpt below.',
                       'documentation' => '[Documentation]',
                       'use_compressor' => 'Use the Gzip compressor:'
                       );
        
        $lang = array(
                      'en-us' => $en_us
                      );
		
		$language = (isset($lang[$language])) ? $language : 'en-us';
		$msg = (isset($lang[$language][$what])) ? $lang[$language][$what] : $what;
		return $msg;
    }

    private function check_install() {
        // Check if the hak_tinymce table already exists
        if (getThings("Show tables like '".PFX."txp_hak_tinymce'")) {
            // if it does check if we need to upgrade
            $pluginversion = safe_field('version','txp_plugin',"name = 'hak_tinymce'");
            $prefs = self::getPrefs();
            $version = (array_key_exists('version', $prefs)) ? $prefs['version'] : "0.0" ;
            
            if (!empty($version) && $version != $pluginversion) {  // if the versions don't match send off to upgrade.
                self::upgrade($version);
            }
            return true;
        }
        return false;
    }

    private function install() {
        
		//figure out what MySQL version we are using (from _update.php)
		$mysqlversion = mysql_get_server_info();
		$tabletype = ( intval($mysqlversion[0]) >= 5 || preg_match('#^4\.(0\.[2-9]|(1[89]))|(1\.[2-9])#',$mysqlversion)) 
            ? " ENGINE=MyISAM "
            : " TYPE=MyISAM ";
		if ( isset($txpcfg['dbcharset']) && (intval($mysqlversion[0]) >= 5 || preg_match('#^4\.[1-9]#',$mysqlversion))) 
            {
                $tabletype .= " CHARACTER SET = ". $txpcfg['dbcharset'] ." ";
            }
		
		// Create the hak_tinymce table
		$hak_tinymce_prefs_table = safe_query("CREATE TABLE `".PFX."txp_hak_tinymce` (
		  `pref_name` VARCHAR(255) NOT NULL, 
		  `pref_value` TEXT NOT NULL,
		  PRIMARY KEY (`pref_name`)
		) $tabletype");
		
		// if the table creation succeeds populate with values
		if ($hak_tinymce_prefs_table) {
			
			extract(get_prefs());
			
			$hak_mceSettings_default = '';
			$hak_mceSettings_default .= "theme : \"advanced\",\n";
			$hak_mceSettings_default .= "language : \"en\",\n";
			$hak_mceSettings_default .= "relative_urls : false,\n";
			$hak_mceSettings_default .= "remove_script_host : false,\n";
			$hak_mceSettings_default .= "plugins : \"searchreplace,txpimage\",\n";
			$hak_mceSettings_default .= "theme_advanced_buttons1 : \"bold,italic,underline,strikethrough,forecolor,backcolor,removeformat,numlist,bullist,outdent,indent,justifyleft,justifycenter,justifyright,justifyfull\",\n";
			$hak_mceSettings_default .= "theme_advanced_buttons2 : \"link,unlink,separator,image,separator,search,replace,separator,cut,copy,paste,separator,code,separator,formatselect\",\n";
			$hak_mceSettings_default .= "theme_advanced_buttons3 : \"\",\n";
			$hak_mceSettings_default .= "theme_advanced_toolbar_location : \"top\",\n";
			$hak_mceSettings_default .= "theme_advanced_toolbar_align : \"left\",";
			
			$hak_mceSettings_compressor = "theme : \"advanced\",\n";
			$hak_mceSettings_compressor .= "plugins : \"searchreplace,txpimage\",\n";
			$hak_mceSettings_compressor .= "disk_cache : true,\n";
			$hak_mceSettings_compressor .= "languages : \"en\",\n";
			$hak_mceSettings_compressor .= "debug : false";
			
			// set pref array values properly checking if it had been setup before.
			$hak_tinymce_prefs["show_toggle"] = (isset($hak_tinymce_show_toggle)) ? $hak_tinymce_show_toggle : "1";
			$hak_tinymce_prefs["hide_on_textile_edit"] = (isset($hak_tinymce_hide_on_textile_edit)) ? $hak_tinymce_hide_on_textile_edit : "1";
			$hak_tinymce_prefs["body_init"] = (isset($hak_tinymce_init_form) && $hak_tinymce_init_form != "hak_tinymce_default") ? fetch_form($hak_tinymce_init_form) : $hak_mceSettings_default;
			$hak_tinymce_prefs["body_init"] .= "\nheight:\"420\",";
			$hak_tinymce_prefs["excerpt_init"] = $hak_mceSettings_default."\nheight:\"150\",";
			$hak_tinymce_prefs["callbacks"] = '';
			$hak_tinymce_prefs["tinymce_path"] = 'tiny_mce/tiny_mce.js';
			$hak_tinymce_prefs["hide_textile_select"] = '0';
			$hak_tinymce_prefs["enable_body"] = '1';
			$hak_tinymce_prefs["enable_excerpt"] = '1';
			
			
			// insert them into the new table
			foreach ($hak_tinymce_prefs as $key => $value) {
				safe_insert("txp_hak_tinymce","pref_name='".$key."', pref_value='".$value."'");
			}
			// Run any necessary upgrades 
            self::upgrade('0.0');
			// delete old prefs
			safe_delete("txp_prefs","name='hak_tinymce_init_form'");
			safe_delete("txp_prefs","name='hak_tinymce_show_toggle'");
			safe_delete("txp_prefs","name='hak_tinymce_hide_on_textile_edit'");
		}
		return true;
    }
    private function upgrade($installedversion) {
        global $plugin;

        if ($installedversion < '0.7') {
            
            $hak_mceSettings_compressor = "theme : \"simple,advanced\",\n";
            $hak_mceSettings_compressor .= "plugins : \"searchreplace,-txpimage\",\n";
            $hak_mceSettings_compressor .= "disk_cache : true,\n";
            $hak_mceSettings_compressor .= "languages : \"en\",\n";
            $hak_mceSettings_compressor .= "debug : false";
            
            $hak_tinymce_prefs["use_compressor"] = '0';
            $hak_tinymce_prefs["compressor_init"] = $hak_mceSettings_compressor;
            
            self::update_version("0.7");
            
            foreach ($hak_tinymce_prefs as $key => $value) {
                if (!safe_field("pref_name", 'txp_hak_tinymce', "pref_name = '".$key."'")) {
                    safe_insert("txp_hak_tinymce","pref_name='".$key."', pref_value='".$value."'");
                } else {
                    safe_update('txp_hak_tinymce', "pref_value = '".$value."'", "pref_name = '".$key."'");
                }
            }
        } // -- End 0.7 upgrade

        // Generic version updater
        dmp($plugin);
        self::update_version($plugin["version"]);
    }
    
    private function update_version($version) {
        safe_upsert('txp_hak_tinymce', "pref_value = '$version'", "pref_name = 'version'");
    }

    private function file_exists($file, $message = "file_not_found") {
        global $path_to_site;
        $out = '';
        if (!file_exists($file) && !file_exists($path_to_site.$file)) {
            $out = br.n.tag(self::mce_gTxt($message),"span",' style="color:red"');
        }
        return $out;
    }

    public function map_attribs($arr) {
        $format = '{src:"%s", width:%s, height:%s, alt:"%s", title:"%s"}';
        $out = sprintf($format, $arr["path"], $arr["width"], $arr["height"],$arr["alt"], $arr["caption"] );
        return $out;
    }
} //--- End Class

//----------------------------------------

//----------------------------------------
function hak_compressor_path($file) {
	$path = str_replace('tiny_mce.js','tiny_mce_gzip.js',$file);
	return $path;
}


//--- Functions for the image browser ----
function hak_txpimage() {
	global $img_dir,$path_to_site,$txpcfg;
	$category = gps("c");
	$category = (!empty($category)) ? "and category='".$category."'" : "";
	$rs = safe_rows_start("*", "txp_image","1=1 ".$category." order by category,name");
	$src = gps("src");

	
	if ($rs) {
		$out = array();
		while ($a = nextRow($rs)) {
			extract($a);
			$selected ='';
			$thumbclick ='';
			$image["path"] = hu.$img_dir.'/'.$id.$ext;
			$image["width"] = $w;
			$image["height"] = $h;
			$image["alt"] = (empty($alt)) ? "" : rawurlencode($alt);
			$image["caption"] = (empty($caption)) ? "" : rawurlencode($caption);
			$onclick = 'onclick=\'TxpImageDialog.insertImage(this,'.hak_tinymce::map_attribs($image).');return'.n.'false;\'';
			
			$preview = $image;
			$thumb = $image;
			
			if($thumbnail) {
				$thumb["path"] = hu.$img_dir.'/'.$id.'t'.$ext;
				$props = @getimagesize($path_to_site.'/'.$thumb["path"]);
				$thumb["width"] = ($props[0]) ? $props[0] : "";
				$thumb["height"] = ($props[1]) ? $props[1] : "";
				$thumb["alt"] = $image["alt"];
				$thumb["caption"] = $image["caption"];
				$preview = $thumb;
				$thumbclick = 'onclick=\'TxpImageDialog.insertImage(this,'.hak_tinymce::map_attribs($thumb).');return'.n.'false;\'';
				$thumbclick = 	'<a href="#" '.$thumbclick.'><img src="img/pictures.png" width="18" height="18" title="'.hak_tinymce::mce_gTxt('insert_thumb').'" alt="'.hak_tinymce::mce_gTxt('insert_thumb').'" /></a>';
			}
			
			//$desiredheight = $preview["height"];
			if ($preview["width"] > $preview["height"]) {
				$new["width"] = 100;
				if (!empty($preview["width"])) {
                    $new["height"] = (100 / $preview["width"]) * $preview["height"];
                } else {
                    $new["height"] = "";
                }
				$margin = (100 - $new["height"]) / 2;
				$margin = intval($margin)."px 0";
			} else {
				$new["height"] = 100;
				if (!empty($preview["height"])) {
                    $new["width"] = (100 / $preview["height"] ) * $preview["width"];
                } else {
                    $new["width"] = "";
                }
				$margin = (100 - $new["width"]) / 2;
				$margin = "0 ".intval($margin)."px";
			}
			
			$out[] = '<div'.$selected.'><div style="padding:'.$margin.'"><img src="'.$preview["path"].'" height="'.$new["height"].'" width="'.$new["width"].'" onclick="window.open(\''.hu.$image["path"].'\',\'mypopup\', \'menubar=0,status=0,height='.$image["height"].',width='.$image["width"].'\')"/></div>'.
                '<a href="#" '.$onclick.'><img src="img/picture.png" width="18" height="18" alt="'.hak_tinymce::mce_gTxt('insert_image').'" title="'.hak_tinymce::mce_gTxt('insert_image').'" /></a>'.
                $thumbclick.
                '</div>';
		}
		echo implode($out,"\n");
		exit(0);
	}
}

function hak_txpcatselect() {
	$rs = getTree("root",'image');
	if ($rs) {
		echo tag(gTxt('category'),"legend").
            treeSelectInput("category",$rs,"","txpCategory");
	}
	exit(0);
}

?>