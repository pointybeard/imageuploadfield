<?php
	
	include_once(EXTENSIONS . '/jit_image_manipulation/lib/class.image.php');
	
	Class FieldImageUpload extends Field {
		public function __construct(&$parent){
			parent::__construct($parent);

			$this->_name = __('Image Upload');
			$this->_required = true;

			$this->set('required', 'yes');
			$this->set('maximum_filesize', NULL);
			$this->set('maximum_dimension_width', NULL);
			$this->set('maximum_dimension_height', NULL);
			$this->set('resize_long_edge_dimension', NULL);
		}

		public function canFilter() {
			return true;
		}

		public function canImport(){
			return true;
		}

		public function isSortable(){
			return true;
		}

		public function buildSortingSQL(&$joins, &$where, &$sort, $order='ASC'){
		    $joins .= "LEFT OUTER JOIN `tbl_entries_data_".$this->get('id')."` AS `ed` ON (`e`.`id` = `ed`.`entry_id`) ";
		    $sort = 'ORDER BY ' . (in_array(strtolower($order), array('random', 'rand')) ? 'RAND()' : "`ed`.`file` $order");
		}

		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation = false) {
			$field_id = $this->get('id');

			if (preg_match('/^mimetype:/', $data[0])) {
				$data[0] = str_replace('mimetype:', '', $data[0]);
				$column = 'mimetype';

			} else if (preg_match('/^size:/', $data[0])) {
				$data[0] = str_replace('size:', '', $data[0]);
				$column = 'size';

			} else {
				$column = 'file';
			}

			if (self::isFilterRegex($data[0])) {
				$this->_key++;
				$pattern = str_replace('regexp:', '', $this->cleanValue($data[0]));
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
						ON (e.id = t{$field_id}_{$this->_key}.entry_id)
				";
				$where .= "
					AND t{$field_id}_{$this->_key}.{$column} REGEXP '{$pattern}'
				";

			} elseif ($andOperation) {
				foreach ($data as $value) {
					$this->_key++;
					$value = $this->cleanValue($value);
					$joins .= "
						LEFT JOIN
							`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
							ON (e.id = t{$field_id}_{$this->_key}.entry_id)
					";
					$where .= "
						AND t{$field_id}_{$this->_key}.{$column} = '{$value}'
					";
				}

			} else {
				if (!is_array($data)) $data = array($data);

				foreach ($data as &$value) {
					$value = $this->cleanValue($value);
				}

				$this->_key++;
				$data = implode("', '", $data);
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
						ON (e.id = t{$field_id}_{$this->_key}.entry_id)
				";
				$where .= "
					AND t{$field_id}_{$this->_key}.{$column} IN ('{$data}')
				";
			}

			return true;
		}

		public function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){

			if(!is_dir(DOCROOT . $this->get('destination') . '/')){
				$flagWithError = __('The destination directory, <code>%s</code>, does not exist.', array($this->get('destination')));
			}

			elseif(!$flagWithError && !is_writable(DOCROOT . $this->get('destination') . '/')){
				$flagWithError = __('Destination folder, <code>%s</code>, is not writable. Please check permissions.', array($this->get('destination')));
			}

			$label = Widget::Label($this->get('label'));
			$class = 'file';
			$label->setAttribute('class', $class);
			if($this->get('required') != 'yes') $label->appendChild(new XMLElement('i', __('Optional')));

			$span = new XMLElement('span');
			if($data['file']) $span->appendChild(Widget::Anchor('/workspace' . $data['file'], URL . '/workspace' . $data['file']));

			$span->appendChild(Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix, $data['file'], ($data['file'] ? 'hidden' : 'file')));

			$label->appendChild($span);

			if($flagWithError != NULL) $wrapper->appendChild(Widget::wrapFormElementWithError($label, $flagWithError));
			else $wrapper->appendChild($label);

		}

		public function entryDataCleanup($entry_id, $data){
			$file_location = WORKSPACE . '/' . ltrim($data['file'], '/');

			if(file_exists($file_location)) General::deleteFile($file_location);

			parent::entryDataCleanup($entry_id);

			return true;
		}		

		public function checkFields(&$errors, $checkForDuplicates=true){

			if(!is_dir(DOCROOT . $this->get('destination') . '/')){
				$errors['destination'] = __('Directory <code>%s</code> does not exist.', array($this->get('destination')));
			}

			elseif(!is_writable(DOCROOT . $this->get('destination') . '/')){
				$errors['destination'] = __('Destination folder, <code>%s</code>, is not writable. Please check permissions.', array($this->get('destination')));
			}
			
			if($this->get('maximum_filesize') != NULL && !is_numeric($this->get('maximum_filesize'))){
				$errors['maximum_filesize'] = __('Invalid value specified. Must be a number.');
			}
			
			if($this->get('maximum_dimension_width') != NULL && !is_numeric($this->get('maximum_dimension_width'))){
				$errors['maximum_dimension_width'] = __('Invalid value specified. Must be a number.');
			}
			
			if($this->get('maximum_dimension_height') != NULL && !is_numeric($this->get('maximum_dimension_height'))){
				$errors['maximum_dimension_height'] = __('Invalid value specified. Must be a number.');
			}	
			
			if($this->get('resize_long_edge_dimension') != NULL && !is_numeric($this->get('resize_long_edge_dimension'))){
				$errors['resize_long_edge_dimension'] = __('Invalid value specified. Must be a number.');
			}

			parent::checkFields($errors, $checkForDuplicates);
		}

		public function commit(){

			if(!parent::commit()) return false;

			$id = $this->get('id');

			if($id === false) return false;

			$fields = array();

			$fields['field_id'] = $id;
			$fields['destination'] = $this->get('destination');
			$fields['maximum_filesize'] = $this->get('maximum_filesize');
			$fields['maximum_dimension_width'] = $this->get('maximum_dimension_width');
			$fields['maximum_dimension_height'] = $this->get('maximum_dimension_height');
			$fields['resize_long_edge_dimension'] = $this->get('resize_long_edge_dimension');

			Symphony::Database()->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");
			return Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle());

		}		

		public function prepareTableValue($data, XMLElement $link=NULL){
			if(!$file = $data['file']) return NULL;

			if($link){
				$link->setValue(basename($file));
				//$view_link = Widget::Anchor('(view)', URL . '/workspace' . $file);
				return $link->generate(); // . ' ' . $view_link->generate();
			}

			else{
				$link = Widget::Anchor(basename($file), URL . '/workspace' . $file);
				return $link->generate();
			}

		}

		public function appendFormattedElement(&$wrapper, $data){
			$item = new XMLElement($this->get('element_name'));
			$file = WORKSPACE . $data['file'];
			$item->setAttributeArray(array(
				'size' => (file_exists($file) && is_readable($file) ? General::formatFilesize(filesize($file)) : 'unknown'),
			 	'path' => str_replace(WORKSPACE, NULL, dirname(WORKSPACE . $data['file'])),
				'type' => $data['mimetype'],
			));

			$item->appendChild(new XMLElement('filename', General::sanitize(basename($data['file']))));

			$m = unserialize($data['meta']);

			if(is_array($m) && !empty($m)){
				$item->appendChild(new XMLElement('meta', NULL, $m));
			}

			$wrapper->appendChild($item);
		}

		public function displaySettingsPanel(&$wrapper, $errors = null) {
			parent::displaySettingsPanel($wrapper, $errors);
			
			$order = $this->get('sortorder');
			
			## Destination Folder
			$ignore = array(
				'/workspace/events',
				'/workspace/data-sources',
				'/workspace/text-formatters',
				'/workspace/pages',
				'/workspace/utilities'
			);
			$directories = General::listDirStructure(WORKSPACE, true, 'asc', DOCROOT, $ignore);	   	

			$label = Widget::Label(__('Destination Directory'));

			$options = array();
			$options[] = array('/workspace', false, '/workspace');
			if(!empty($directories) && is_array($directories)){
				foreach($directories as $d) {
					$d = '/' . trim($d, '/');
					if(!in_array($d, $ignore)) $options[] = array($d, ($this->get('destination') == $d), $d);
				}	
			}

			$label->appendChild(Widget::Select('fields['.$this->get('sortorder').'][destination]', $options));

			if(isset($errors['destination'])) $wrapper->appendChild(Widget::wrapFormElementWithError($label, $errors['destination']));
			else $wrapper->appendChild($label);

			//$this->buildValidationSelect($wrapper, $this->get('validator'), 'fields['.$this->get('sortorder').'][validator]', 'upload');
			
			/*---------------------------------------------------------------------
				Limiting
			---------------------------------------------------------------------*/

				$group = new XMLElement('div');
				$group->setAttribute('class', 'group triple');

				$input = Widget::Input(
					"fields[{$order}][maximum_filesize]",
					$this->get('maximum_filesize')
				);
				$input->setAttribute('size', '12');

				$label = Widget::Label(
					__('Limit image file size to %s MB', array(
						$input->generate()
					))
				);
				
				if(isset($errors['maximum_filesize'])) $group->appendChild(Widget::wrapFormElementWithError($label, $errors['maximum_filesize']));
				else $group->appendChild($label);
			
				$input = Widget::Input(
					"fields[{$order}][maximum_dimension_width]",
					$this->get('maximum_dimension_width')
				);
				$input->setAttribute('size', '6');

				$label = Widget::Label(
					__('Limit image width size to %s pixels', array(
						$input->generate()
					))
				);
				
				if(isset($errors['maximum_dimension_width'])) $group->appendChild(Widget::wrapFormElementWithError($label, $errors['maximum_dimension_width']));
				else $group->appendChild($label);

				$input = Widget::Input(
					"fields[{$order}][maximum_dimension_height]",
					$this->get('maximum_dimension_height')
				);
				$input->setAttribute('size', '6');

				$label = Widget::Label(
					__('Limit image height size to %s pixels', array(
						$input->generate()
					))
				);
				
				if(isset($errors['maximum_dimension_height'])) $group->appendChild(Widget::wrapFormElementWithError($label, $errors['maximum_dimension_height']));
				else $group->appendChild($label);
				
				$wrapper->appendChild($group);

				$input = Widget::Input(
					"fields[{$order}][resize_long_edge_dimension]",
					$this->get('resize_long_edge_dimension')
				);
				$input->setAttribute('size', '6');

				$label = Widget::Label(
					__('Resize images to have a long edge of %s pixels, maintaining aspect. <em>(Note: This should be less than any width or height restrictions above.)</em>', array(
						$input->generate()
					))
				);
				
				if(isset($errors['resize_long_edge_dimension'])) $wrapper->appendChild(Widget::wrapFormElementWithError($label, $errors['resize_long_edge_dimension']));
				else $wrapper->appendChild($label);
				
		
				
						
			$this->appendRequiredCheckbox($wrapper);
			$this->appendShowColumnCheckbox($wrapper);

		}

		public function checkPostFieldData($data, &$message, $entry_id=NULL){
			
			if (is_array($data) and isset($data['name'])) $data['name'] = $this->getUniqueFilename($data['name']);
			
			/*
				UPLOAD_ERR_OK
				Value: 0; There is no error, the file uploaded with success.

				UPLOAD_ERR_INI_SIZE
				Value: 1; The uploaded file exceeds the upload_max_filesize directive in php.ini.

				UPLOAD_ERR_FORM_SIZE
				Value: 2; The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.

				UPLOAD_ERR_PARTIAL
				Value: 3; The uploaded file was only partially uploaded.

				UPLOAD_ERR_NO_FILE
				Value: 4; No file was uploaded.

				UPLOAD_ERR_NO_TMP_DIR
				Value: 6; Missing a temporary folder. Introduced in PHP 4.3.10 and PHP 5.0.3.

				UPLOAD_ERR_CANT_WRITE
				Value: 7; Failed to write file to disk. Introduced in PHP 5.1.0.

				UPLOAD_ERR_EXTENSION
				Value: 8; File upload stopped by extension. Introduced in PHP 5.2.0.
			*/

		//	Array
		//	(
		//	    [name] => filename.pdf
		//	    [type] => application/pdf
		//	    [tmp_name] => /tmp/php/phpYtdlCl
		//	    [error] => 0
		//	    [size] => 16214
		//	)

			$message = NULL;

			if(empty($data) || $data['error'] == UPLOAD_ERR_NO_FILE) {

				if($this->get('required') == 'yes'){
					$message = __("'%s' is a required field.", array($this->get('label')));
					return self::__MISSING_FIELDS__;		
				}

				return self::__OK__;
			}

			## Its not an array, so just retain the current data and return
			if(!is_array($data)){
				
				$file = WORKSPACE . $data;
				
				if(!file_exists($file) || !is_readable($file)){
					$message = __('The file uploaded is no longer available. Please check that it exists, and is readable.');
					return self::__INVALID_FIELDS__;
				}
				
				return self::__OK__;
			}


			if(!is_dir(DOCROOT . $this->get('destination') . '/')){
				$message = __('The destination directory, <code>%s</code>, does not exist.', array($this->get('destination')));
				return self::__ERROR__;
			}

			elseif(!is_writable(DOCROOT . $this->get('destination') . '/')){
				$message = __('Destination folder, <code>%s</code>, is not writable. Please check permissions.', array($this->get('destination')));
				return self::__ERROR__;
			}

			if($data['error'] != UPLOAD_ERR_NO_FILE && $data['error'] != UPLOAD_ERR_OK){

				switch($data['error']){

					case UPLOAD_ERR_INI_SIZE:
						$message = __('File chosen in "%1$s" exceeds the maximum allowed upload size of %2$s specified by your host.', array($this->get('label'), (is_numeric(ini_get('upload_max_filesize')) ? General::formatFilesize(ini_get('upload_max_filesize')) : ini_get('upload_max_filesize'))));
						break;

					case UPLOAD_ERR_FORM_SIZE:
						$message = __('File chosen in "%1$s" exceeds the maximum allowed upload size of %2$s, specified by Symphony.', array($this->get('label'), General::formatFilesize(Symphony::Configuration()->get('max_upload_size', 'admin'))));
						break;

					case UPLOAD_ERR_PARTIAL:
						$message = __("File chosen in '%s' was only partially uploaded due to an error.", array($this->get('label')));
						break;

					case UPLOAD_ERR_NO_TMP_DIR:
						$message = __("File chosen in '%s' was only partially uploaded due to an error.", array($this->get('label')));
						break;

					case UPLOAD_ERR_CANT_WRITE:
						$message = __("Uploading '%s' failed. Could not write temporary file to disk.", array($this->get('label')));
						break;

					case UPLOAD_ERR_EXTENSION:
						$message = __("Uploading '%s' failed. File upload stopped by extension.", array($this->get('label')));
						break;

				}

				return self::__ERROR_CUSTOM__;

			}

			## Sanitize the filename
			$data['name'] = Lang::createFilename($data['name']);

/*
			if($this->get('validator') != NULL){
				$rule = $this->get('validator');

				if(!General::validateString($data['name'], $rule)){
					$message = __("File chosen in '%s' does not match allowable file types for that field.", array($this->get('label')));
					return self::__INVALID_FIELDS__;
				}

			}

			$fields['maximum_filesize'] = $this->get('maximum_filesize');
			$fields['maximum_dimension_width'] = $this->get('maximum_dimension_width');
			$fields['maximum_dimension_height'] = $this->get('maximum_dimension_height');

*/	

			if($this->get('maximum_filesize') != NULL && filesize($data['tmp_name']) > ($this->get('maximum_filesize') * 1024 * 1024)){
				$message = __('Image exceeds the maximum allowed upload size of %1$d MB.', array($this->get('maximum_filesize')));
				return self::__INVALID_FIELDS__;
			}
			
			$meta = Image::getMetaInformation($data['tmp_name']);
			if($meta === false){
				$message = __('Image is not a valid image file, as it could not be read. Must be PNG, JPG or GIF.');
				return self::__INVALID_FIELDS__;
			}
			
			if(
				($this->get('maximum_dimension_width') != NULL && $meta->width > $this->get('maximum_dimension_width'))
				OR
				($this->get('maximum_dimension_height') != NULL && $meta->height > $this->get('maximum_dimension_height'))
			){
				$message = __(
					'Image exceeds the maximum allowed dimensions of %1$d x %2$d pixels.', 
					array($this->get('maximum_dimension_width'), $this->get('maximum_dimension_height'))
				);
				return self::__INVALID_FIELDS__;
			}

			$abs_path = DOCROOT . '/' . trim($this->get('destination'), '/');
			$new_file = $abs_path . '/' . $data['name'];
			$existing_file = NULL;

			if($entry_id){
				$row = $this->Database->fetchRow(0, "SELECT * FROM `tbl_entries_data_".$this->get('id')."` WHERE `entry_id` = '$entry_id' LIMIT 1");
				$existing_file = $abs_path . '/' . basename($row['file'], '/');
			}

			if((strtolower($existing_file) != strtolower($new_file)) && file_exists($new_file)){
				$message = __('A file with the name %1$s already exists in %2$s. Please rename the file first, or choose another.', array($data['name'], $this->get('destination')));
				return self::__INVALID_FIELDS__;
			}

			return self::__OK__;

		}

		public function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL){

			$status = self::__OK__;

			## Its not an array, so just retain the current data and return
			if(!is_array($data)){
	
				$status = self::__OK__;
				
				$file = WORKSPACE . $data;
				
				$result = array(
					'file' => $data,
					'mimetype' => NULL,
					'size' => NULL,
					'meta' => NULL
				);
				
				// Grab the existing entry data to preserve the MIME type and size information
				if(isset($entry_id) && !is_null($entry_id)){
					$row = Symphony::Database()->fetchRow(0, sprintf(
						"SELECT `file`, `mimetype`, `size`, `meta` FROM `tbl_entries_data_%d` WHERE `entry_id` = %d", 
						$this->get('id'), 
						$entry_id
					));
					if(!empty($row)){
						$result = $row;
					}
				}
				
				if(!file_exists($file) || !is_readable($file)){
					$status = self::__INVALID_FIELDS__;
					return $result;
				}

				return $result;
	
			}

			if($simulate) return;

			if (is_array($data) and isset($data['name'])) $data['name'] = $this->getUniqueFilename($data['name']);

			## Upload the new file
			$abs_path = DOCROOT . '/' . trim($this->get('destination'), '/');
			$rel_path = str_replace('/workspace', '', $this->get('destination'));
			$existing_file = NULL;

			if(!is_null($entry_id)){
				$row = Symphony::Database()->fetchRow(0, sprintf(
					"SELECT * FROM `tbl_entries_data_%s` WHERE `entry_id` = %d LIMIT 1", 
					$this->get('id'), 
					$entry_id
				));

				$existing_file = rtrim($rel_path, '/') . '/' . trim(basename($row['file']), '/');

				// File was removed
				if($data['error'] == UPLOAD_ERR_NO_FILE && !is_null($existing_file) && file_exists(WORKSPACE . $existing_file)){
					General::deleteFile(WORKSPACE . $existing_file);
				}
			}

			if($data['error'] == UPLOAD_ERR_NO_FILE || $data['error'] != UPLOAD_ERR_OK){
				return;
			}

			## Sanitize the filename
			$data['name'] = Lang::createFilename($data['name']);
			
			// Do any pre-processing
			$meta = Image::getMetaInformation($data['tmp_name']);
			if(
				$this->get('resize_long_edge_dimension') != NULL 
				AND 
				($meta->width > $this->get('resize_long_edge_dimension') || $meta->height > $this->get('resize_long_edge_dimension'))
			){
				try{
					$image = Image::load($data['tmp_name']);
					$dest_width = $dest_height = NULL;
					if($image->Meta()->width > $image->Meta()->height){
						$dest_width = $this->get('resize_long_edge_dimension');
					}
					else{
						$dest_height = $this->get('resize_long_edge_dimension');
					}
						
					$image->applyFilter('resize', array($dest_width, $dest_height));
					$image->save($abs_path . '/' . $data['name'], 100);
				}
				catch(Exception $e){
					$message = __('There was an error while trying to pre-process the file <code>%s</code>: %s.', array($data['name'], $e->getMessage()));
					$status = self::__ERROR_CUSTOM__;
				}
			}else{
			
				if(!General::uploadFile($abs_path, $data['name'], $data['tmp_name'], Symphony::Configuration()->get('write_mode', 'file'))){

					$message = __('There was an error while trying to upload the file <code>%1$s</code> to the target directory <code>%2$s</code>.', array($data['name'], 'workspace/'.ltrim($rel_path, '/')));
					$status = self::__ERROR_CUSTOM__;
					return;
				}
				
			}
			
			$status = self::__OK__;

			$file = rtrim($rel_path, '/') . '/' . trim($data['name'], '/');

			// File has been replaced
			if(!is_null($existing_file) && (strtolower($existing_file) != strtolower($file)) && file_exists(WORKSPACE . $existing_file)){
				General::deleteFile(WORKSPACE . $existing_file);
			}

			## If browser doesn't send MIME type (e.g. .flv in Safari)
			if (strlen(trim($data['type'])) == 0){
				$data['type'] = 'unknown';
			}

			return array(
				'file' => $file,
				'size' => $data['size'],
				'mimetype' => $data['type'],
				'meta' => serialize(self::getMetaInfo(WORKSPACE . $file, $data['type']))
			);

		}

		private static function __sniffMIMEType($file){

			$ext = strtolower(General::getExtension($file));

			$imageMimeTypes = array(
				'image/gif',
				'image/jpg',
				'image/jpeg',
				'image/png',
			);

			if(General::in_iarray("image/{$ext}", $imageMimeTypes)) return "image/{$ext}";

			return 'unknown';
		}

		public static function getMetaInfo($file, $type){

			$imageMimeTypes = array(
				'image/gif',
				'image/jpg',
				'image/jpeg',
				'image/png',
			);

			$meta = array();

			$meta['creation'] = DateTimeObj::get('c', filemtime($file));

			if(General::in_iarray($type, $imageMimeTypes) && $array = @getimagesize($file)){
				$meta['width']    = $array[0];
				$meta['height']   = $array[1];
			}

			return $meta;

		}

		private function getUniqueFilename($filename) {
			// since unix timestamp is 10 digits, the unique filename will be limited to ($crop+1+10) characters;
			$crop  = '33';
			return preg_replace("/(.*)(\.[^\.]+)/e", "substr('$1', 0, $crop).'-'.time().'$2'", $filename);
		}
		
		public function createTable(){

			return Symphony::Database()->query(

				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `file` varchar(255) default NULL,
				  `size` int(11) unsigned NOT NULL,
				  `mimetype` varchar(50) default NULL,
				  `meta` varchar(255) default NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				  KEY `file` (`file`),
				  KEY `mimetype` (`mimetype`)
				) TYPE=MyISAM ;	"

			);
		}		

		public function getExampleFormMarkup(){
			$label = Widget::Label($this->get('label'));
			$label->appendChild(Widget::Input('fields['.$this->get('element_name').']', NULL, 'file'));

			return $label;
		}

	}

