<?php
/**
 * Modifcation XML Documentation can be found here:
 *
 * https://github.com/opencart/opencart/wiki/Modification-System
 * https://github.com/vqmod/vqmod/wiki/Scripting
 */
class ControllerExtensionModification extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/modification');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/modification');

		$this->getList();
	}

	public function delete() {
		$this->load->language('extension/modification');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/modification');

		if (isset($this->request->post['selected']) && $this->validate()) {
			foreach ($this->request->post['selected'] as $modification_id) {
				$this->model_extension_modification->deleteModification($modification_id);
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('extension/modification', 'token=' . $this->session->data['token'] . $url, 'SSL'));
		}

		$this->getList();
	}

	protected function vqmodGetIndexes( $search_node_index ) {
		if ($search_node_index) {
			$tmp = explode(',', $search_node_index);
			foreach ($tmp as $k => $v) {
				if (!is_int($v)) {
					unset($k);
				}
			}
			$tmp = array_unique($tmp);
			return empty($tmp) ? false : $tmp;
		} else {
			return false;
		}
	}

	protected function vqmodGetFileKey( $file ) {
		// Get the key to be used for the modification cache filename.
		$key = '';
		if (substr($file, 0, strlen(DIR_CATALOG)) == DIR_CATALOG) {
			$key = 'catalog/' . substr($file, strlen(DIR_CATALOG));
		}
		if (substr($file, 0, strlen(DIR_APPLICATION)) == DIR_APPLICATION) {
			$key = 'admin/' . substr($file, strlen(DIR_APPLICATION));
		}
		if (substr($file, 0, strlen(DIR_SYSTEM)) == DIR_SYSTEM) {
			$key = 'system/' . substr($file, strlen(DIR_SYSTEM));
		}
		return $key;
	}

	protected function vqmodWrite( DOMDocument $dom, &$modification, &$original, &$log ) {
		$modification_node = $dom->getElementsByTagName('modification')->item(0);
		$file_nodes = $modification_node->getElementsByTagName('file');
		$modification_id = $modification_node->getElementsByTagName('id')->item(0)->nodeValue;

		$log[] = "Modification::refresh - Processing '". $modification_id ."'";

		$version = '2.5.1';
		$vqmver = $modification_node->getElementsByTagName('vqmver')->item(0);
		if ($vqmver) {
			$version_check = $vqmver->getAttribute('required');
			if (strtolower($version_check) == 'true') {
				if (version_compare($version, $vqmver->nodeValue, '<')) {
					$log[] = "Modification::refresh - VQMOD VERSION '" . $vqmver->nodeValue . "' OR ABOVE REQUIRED, XML FILE HAS BEEN SKIPPED";
					$log[] = "  vqmver = '$vqmver'";
					$log[] = '----------------------------------------------------------------';
					return;
				}
			}
		}

		foreach ($file_nodes as $file_node) {

			$file_node_path = $file_node->getAttribute('path');
			$file_node_name = $file_node->getAttribute('name');
			$file_node_error = $file_node->getAttribute('error');

			// find all files to be modified
			$files = array();
			$file_names = explode( ',', $file_node_name );
			if ($file_names===false) {
				 $file_names = array();
			}
			foreach ($file_names as $file_name) {
				$path = '';
				if (substr($file_node_path.$file_name, 0, 7) == 'catalog') {
					$path = DIR_CATALOG . substr($file_node_path.$file_name, 8);
				} else if (substr($file_node_path.$file_name, 0, 5) == 'admin') {
					$path = DIR_APPLICATION . substr($file_node_path.$file_name, 6);
				} else if (substr($file_node_path.$file_name, 0, 6) == 'system') {
					$path = DIR_SYSTEM . substr($file_node_path.$file_name, 7);
				}
				$paths = glob($path);
				if (($paths===false) || is_array($paths) && (count($paths)==0)) {
					switch ($file_node_error) {
						case 'skip':
							break;
						case 'abort':
							$log[] = "Modification::refresh - UNABLE TO FIND FILE(S), XML PARSING ABORTED:";
							$log[] = "  file = '$path'";
							$log[] = '----------------------------------------------------------------';
							return;
						case 'log':
						default:
							$log[] = "Modification::refresh - UNABLE TO FIND FILE(S), IGNORED:";
							$log[] = "  file = '$path'";
							break;
					}
				} else {
					foreach ($paths as $file) {
						if (is_file($file)) {
							$files[] = $file;
						} else {
							switch ($file_node_error) {
								case 'skip':
									break;
								case 'abort':
									$log[] = "Modification::refresh - NOT A FILE, XML PARSING ABORTED:";
									$log[] = "  file = '$file'";
									$log[] = '----------------------------------------------------------------';
									return;
								case 'log':
								default:
									$log[] = "Modification::refresh - NOT A FILE, IGNORED:";
									$log[] = "  file = '$file'";
									break;
							}
						}
					}
				}
			}

			$operation_nodes = $file_node->getElementsByTagName('operation');
			
			foreach ($files as $file) {
				$key = $this->vqmodGetFileKey( $file );
				if ($key=='') {
					$log[] = "Modification::refresh - UNABLE TO GENERATE FILE KEY:";
					$log[] = "  file name = '$file'";
					continue;
				}
				if (!isset($modification[$key])) {
					$modification[$key] = preg_replace('~\r?\n~', "\n", file_get_contents($file));
					$original[$key] = $modification[$key];
				}

				foreach ($operation_nodes as $operation_node) {
					$operation_node_error = $operation_node->getAttribute('error');
					if (($operation_node_error != 'skip') && ($operation_node_error != 'log')) {
						$operation_node_error = 'abort';
					}

					$ignoreif_node = $operation_node->getElementsByTagName('ignoreif')->item(0);
					if ($ignoreif_node) {
						$ignoreif_node_regex = $ignoreif_node->getAttribute('regex');
						$ignoreif_node_value = trim( $ignoreif_node->nodeValue );
						if ($ignoreif_node_regex == 'true') {
							if (preg_match($ignoreif_node_value, $modification[$key])) {
								continue;
							}
						} else {
							if (strpos($modification[$key], $ignoreif_node_value) !== false) {
								continue;
							}
						}
					}

					$search_node = $operation_node->getElementsByTagName('search')->item(0);
					$search_node_position = ($search_node->getAttribute('position')) ? $search_node->getAttribute('position') : 'replace';
					$search_node_indexes = $this->vqmodGetIndexes( $search_node->getAttribute('index') );
					$search_node_offset = ($search_node->getAttribute('offset')) ? $search_node->getAttribute('offset') : '0';
					$search_node_regex = ($search_node->getAttribute('regex')) ? $search_node->getAttribute('regex') : 'false';
					$search_node_trim = ($search_node->getAttribute('trim')=='false') ? 'false' : 'true';
					$search_node_value = ($search_node_trim=='true') ? trim($search_node->nodeValue) : $search_node->nodeValue;

					$add_node = $operation_node->getElementsByTagName('add')->item(0);
					$add_node_trim = ($add_node->getAttribute('trim')=='true') ? 'true' : 'false';
					$add_node_value = ($add_node_trim=='true') ? trim($add_node->nodeValue) : $add_node->nodeValue;

					$index_count = 0;
					$tmp = explode("\n",$modification[$key]);
					$line_max = count($tmp)-1;

					// apply the next search and add operation to the file content
					switch ($search_node_position) {
						case 'top':
							$tmp[(int)$search_node_offset] = $add_node_value . $tmp[(int)$search_node_offset];
							break;
						case 'bottom':
							$offset = $line_max - (int)$search_node_offset;
							if ($offset < 0) {
								$tmp[-1] = $add_node_value;
							} else {
								$tmp[$offset] .= $add_node_value;;
							}
							break;
						default:
							$changed = false;
							foreach ($tmp as $line_num => $line) {
								if (strlen($search_node_value) == 0) {
									if ($operation_node_error == 'log' || $operation_node_error == 'abort') {
										$log[] = "Modification::refresh - EMPTY SEARCH CONTENT ERROR:";
										$log[] = "  file name = '$file'";
									}
									break;
								}
								
								if ($search_node_regex == 'true') {
									$pos = @preg_match($search_node_value, $line);
									if ($pos === false) {
										if ($operation_node_error == 'log' || $operation_node_error == 'abort') {
											$log[] = "Modification::refresh - INVALID REGEX ERROR:";
											$log[] = "  file name = '$file'";
											$log[] = "  search = '$search_node_value'";
										}
										continue 2; // continue with next operation_node
									} elseif ($pos == 0) {
										$pos = false;
									}
								} else {
									$pos = strpos($line, $search_node_value);
								}

								if ($pos !== false) {
									$index_count++;
									$changed = true;

									if (!$search_node_indexes || ($search_node_indexes && in_array($index_count, $search_node_indexes))) {
										switch ($search_node_position) {
											case 'before':
												$offset = ($line_num - $search_node_offset < 0) ? -1 : $line_num - $search_node_offset;
												$tmp[$offset] = empty($tmp[$offset]) ? $add_node_value : $add_node_value . "\n" . $tmp[$offset];
												break;
											case 'after':
												$offset = ($line_num + $search_node_offset > $line_max) ? $line_max : $line_num + $search_node_offset;
												$tmp[$offset] = $tmp[$offset] . "\n" . $add_node_value;
												break;
											case 'ibefore':
												$tmp[$line_num] = str_replace($search_node_value, $add_node_value . $search_node_value, $line);
												break;
											case 'iafter':
												$tmp[$line_num] = str_replace($search_node_value, $search_node_value . $add_node_value, $line);
												break;
											default:
												if (!empty($search_node_offset)) {
													for ($i = 1; $i <= $search_node_offset; $i++) {
														if (isset($tmp[$line_num + $i])) {
															$tmp[$line_num + $i] = '';
														}
													}
												}
												if ($search_node_regex == 'true') {
													$tmp[$line_num] = preg_replace( $search_node_value, $add_node_value, $line);
												} else {
													$tmp[$line_num] = str_replace( $search_node_value, $add_node_value, $line);
												}
												break;
										}
									}
								}
							}

							if (!$changed) {
								$skip_text = ($operation_node_error == 'skip' || $operation_node_error == 'log') ? '(SKIPPED)' : '(ABORTING MOD)';
								if ($operation_node_error == 'log' || $operation_node_error) {
									$log[] = "Modification::refresh - SEARCH NOT FOUND $skip_text:";
									$log[] = "  file name = '$file'";
									$log[] = "  search = '$search_node_value'";
								}

								if ($operation_node_error == 'abort') {
									$log[] = '----------------------------------------------------------------';
									return; // skip this XML file
								}
							}
							break;
					}

					ksort($tmp);

					$modification[$key] = implode("\n", $tmp);

				} // $operation_nodes
			} // $files
		} // $file_nodes

		$log[] = "Modification::refresh - Done '". $modification_id ."'";
		$log[] = '----------------------------------------------------------------';

	}


	protected function isVqmod( DOMDocument $dom ) {
		$modification_node = $dom->getElementsByTagName('modification')->item(0);
		if ($modification_node) {
			$vqmver_node = $modification_node->getElementsByTagName('vqmver')->item(0);
			if ($vqmver_node) {
				return true;
			}
		}
		return false;
	}


	public function refresh() {
		$this->load->language('extension/modification');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/modification');

		if ($this->validate()) {
			//Log
			$log = array();

			// Clear all modification files
			$files = array();
			
			// Make path into an array
			$path = array(DIR_MODIFICATION . '*');

			// While the path array is still populated keep looping through
			while (count($path) != 0) {
				$next = array_shift($path);

				foreach (glob($next) as $file) {
					// If directory add to path array
					if (is_dir($file)) {
						$path[] = $file . '/*';
					}

					// Add the file to the files to be deleted array
					$files[] = $file;
				}
			}
			
			// Reverse sort the file array
			rsort($files);
			
			// Clear all modification files
			foreach ($files as $file) {
				if ($file != DIR_MODIFICATION . 'index.html') {
					// If file just delete
					if (is_file($file)) {
						unlink($file);
	
					// If directory use the remove directory function
					} elseif (is_dir($file)) {
						rmdir($file);
					}
				}
			}	
			
			// Begin
			$xml = array();

			// Load the default modification XML
			$xml[] = file_get_contents(DIR_SYSTEM . 'modification.xml');
			
			// This is purly for developers so they can run mods directly and have them run without upload sfter each change.
			$files = glob(DIR_SYSTEM.'*.{ocmod,vqmod}.xml',GLOB_BRACE);

			if ($files) {
				foreach ($files as $file) {
					$xml[] = file_get_contents($file);
				}
			}

			// For traditional VQmod files loaded from 'vqmod/xml' folder, to have them run without upload after each change.
			$files = is_dir(DIR_SYSTEM.'../vqmod/xml') ? glob(DIR_SYSTEM.'../vqmod/xml/*.xml') : array();

			if ($files) {
				foreach ($files as $file) {
					$xml[] = file_get_contents($file);
				}
			}

			// Get the default modification file
			$results = $this->model_extension_modification->getModifications();

			foreach ($results as $result) {
				if ($result['status']) {
					$xml[] = $result['xml'];
				}
			}

			$modification = array();
			$original = array();

			foreach ($xml as $xml) {
				$dom = new DOMDocument('1.0', 'UTF-8');
				$dom->preserveWhiteSpace = false;
				$dom->loadXml($xml);

				if ($this->isVqmod( $dom )) {
					$this->vqmodWrite( $dom, $modification, $original, $log );
					continue;
				}

				// Wipe the past modification store in the backup array
				$recovery = array();
				
				// Set the a recovery of the modification code in case we need to use it if an abort attribute is used.
				if (isset($modification)) {
					$recovery = $modification;
				}
								
				// Log
				$log[] = 'MOD: ' . $dom->getElementsByTagName('name')->item(0)->textContent;

				$files = $dom->getElementsByTagName('modification')->item(0)->getElementsByTagName('file');

				foreach ($files as $file) {
					$operations = $file->getElementsByTagName('operation');

					$path = '';

					// Get the full path of the files that are going to be used for modification
					if (substr($file->getAttribute('path'), 0, 7) == 'catalog') {
						$path = DIR_CATALOG . str_replace('../', '', substr($file->getAttribute('path'), 8));
					}

					if (substr($file->getAttribute('path'), 0, 5) == 'admin') {
						$path = DIR_APPLICATION . str_replace('../', '', substr($file->getAttribute('path'), 6));
					}

					if (substr($file->getAttribute('path'), 0, 6) == 'system') {
						$path = DIR_SYSTEM . str_replace('../', '', substr($file->getAttribute('path'), 7));
					}

					if ($path) {
						$files = glob($path, GLOB_BRACE);

						if ($files) {
							foreach ($files as $file) {
								// Get the key to be used for the modification cache filename.
								if (substr($file, 0, strlen(DIR_CATALOG)) == DIR_CATALOG) {
									$key = 'catalog/' . substr($file, strlen(DIR_CATALOG));
								}

								if (substr($file, 0, strlen(DIR_APPLICATION)) == DIR_APPLICATION) {
									$key = 'admin/' . substr($file, strlen(DIR_APPLICATION));
								}

								if (substr($file, 0, strlen(DIR_SYSTEM)) == DIR_SYSTEM) {
									$key = 'system/' . substr($file, strlen(DIR_SYSTEM));
								}
								
								// If file contents is not already in the modification array we need to load it.
								if (!isset($modification[$key])) {
									$content = file_get_contents($file);

									$modification[$key] = preg_replace('~\r?\n~', "\n", $content);
									$original[$key] = preg_replace('~\r?\n~', "\n", $content);

									// Log
									$log[] = 'FILE: ' . $key;
								}
								
								foreach ($operations as $operation) {
									$error = $operation->getAttribute('error');
									
									// Ignoreif
									$ignoreif = $operation->getElementsByTagName('ignoreif')->item(0);
									
									if ($ignoreif) {
										if ($ignoreif->getAttribute('regex') != 'true') {
											if (strpos($modification[$key], $ignoreif->textContent) !== false) {
												continue;
											}												
										} else {
											if (preg_match($ignoreif->textContent, $modification[$key])) {
												continue;
											}
										}
									}
									
									$status = false;
									
									// Search and replace
									if ($operation->getElementsByTagName('search')->item(0)->getAttribute('regex') != 'true') {
										// Search
										$search = $operation->getElementsByTagName('search')->item(0)->textContent;
										$trim = $operation->getElementsByTagName('search')->item(0)->getAttribute('trim');
										$index = $operation->getElementsByTagName('search')->item(0)->getAttribute('index');
										
										// Trim line if no trim attribute is set or is set to true.
										if (!$trim || $trim == 'true') {
											$search = trim($search);
										}
																				
										// Add
										$add = $operation->getElementsByTagName('add')->item(0)->textContent;
										$trim = $operation->getElementsByTagName('add')->item(0)->getAttribute('trim');
										$position = $operation->getElementsByTagName('add')->item(0)->getAttribute('position');
										$offset = $operation->getElementsByTagName('add')->item(0)->getAttribute('offset');										
										
										if ($offset == '') {
                                            $offset = 0;
                                        }

										// Trim line if is set to true.
										if ($trim == 'true') {
											$add = trim($add);
										}
										
										// Log
										$log[] = 'CODE: ' . $search;
										
										// Check if using indexes
										if ($index !== '') {
											$indexes = explode(',', $index);
										} else {
											$indexes = array();
										}
										
										// Get all the matches
										$i = 0;
										
										$lines = explode("\n", $modification[$key]);

										for ($line_id = 0; $line_id < count($lines); $line_id++) {
											$line = $lines[$line_id];

											// Status
											$match = false;
											
											// Check to see if the line matches the search code.
											if (stripos($line, $search) !== false) {
												// If indexes are not used then just set the found status to true.
												if (!$indexes) {
													$match = true;
												} elseif (in_array($i, $indexes)) {
													$match = true;
												}
												
												$i++;
											}
											
											// Now for replacing or adding to the matched elements
											if ($match) {
												switch ($position) {
													default:
													case 'replace':
														if ($offset < 0) {
															array_splice($lines, $line_id + $offset, abs($offset) + 1, array(str_replace($search, $add, $line)));

															$line_id -= $offset;
														} else {
															array_splice($lines, $line_id, $offset + 1, array(str_replace($search, $add, $line)));
														}
														break;
													case 'before':
														$new_lines = explode("\n", $add);

														array_splice($lines, $line_id - $offset, 0, $new_lines);

														$line_id += count($new_lines);
														break;
													case 'after':
														array_splice($lines, ($line_id + 1) + $offset, 0, explode("\n", $add));
														break;
												}
												
												// Log
												$log[] = 'LINE: ' . $line_id;
												
												$status = true;										
											}
										}
										
										$modification[$key] = implode("\n", $lines);
									} else {									
										$search = $operation->getElementsByTagName('search')->item(0)->textContent;
										$limit = $operation->getElementsByTagName('search')->item(0)->getAttribute('limit');
										$replace = $operation->getElementsByTagName('add')->item(0)->textContent;
										
										// Limit
										if (!$limit) {
											$limit = -1;
										}

										// Log
										$match = array();

										preg_match_all($search, $modification[$key], $match, PREG_OFFSET_CAPTURE);

										// Remove part of the the result if a limit is set.
										if ($limit > 0) {
											$match[0] = array_slice($match[0], 0, $limit);
										}

										if ($match[0]) {
											$log[] = 'REGEX: ' . $search;

											for ($i = 0; $i < count($match[0]); $i++) {
												$log[] = 'LINE: ' . (substr_count(substr($modification[$key], 0, $match[0][$i][1]), "\n") + 1);
											}
											
											$status = true;
										}

										// Make the modification
										$modification[$key] = preg_replace($search, $replace, $modification[$key], $limit);
									}
									
									if (!$status) {
										// Log
										$log[] = 'NOT FOUND!';

										// Skip current operation
										if ($error == 'skip') {
											break;
										}
										
										// Abort applying this modification completely.
										if ($error == 'abort') {
											$modification = $recovery;
											
											// Log
											$log[] = 'ABORTING!';
										
											break 4;
										}
									}									
								}
							}
						}
					}
				}
				
				// Log
				$log[] = '----------------------------------------------------------------';				
			}

			// Log
			$ocmod = new Log('ocmod.log');
			$ocmod->write(implode("\n", $log));

			// Write all modification files
			foreach ($modification as $key => $value) {
				// Only create a file if there are changes
				if ($original[$key] != $value) {
					$path = '';

					$directories = explode('/', dirname($key));

					foreach ($directories as $directory) {
						$path = ($path=='') ? $directory : $path . '/' . $directory;

						if (!is_dir(DIR_MODIFICATION . $path)) {
							@mkdir(DIR_MODIFICATION . $path, 0777);
						}
					}

					$handle = fopen(DIR_MODIFICATION . $key, 'w');

					fwrite($handle, $value);

					fclose($handle);
				}
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('extension/modification', 'token=' . $this->session->data['token'] . $url, 'SSL'));
		}

		$this->getList();
	}

	public function clear() {
		$this->load->language('extension/modification');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/modification');

		if ($this->validate()) {
			$files = array();
			
			// Make path into an array
			$path = array(DIR_MODIFICATION . '*');

			// While the path array is still populated keep looping through
			while (count($path) != 0) {
				$next = array_shift($path);

				foreach (glob($next) as $file) {
					// If directory add to path array
					if (is_dir($file)) {
						$path[] = $file . '/*';
					}

					// Add the file to the files to be deleted array
					$files[] = $file;
				}
			}
			
			// Reverse sort the file array
			rsort($files);
			
			// Clear all modification files
			foreach ($files as $file) {
				if ($file != DIR_MODIFICATION . 'index.html') {
					// If file just delete
					if (is_file($file)) {
						unlink($file);
	
					// If directory use the remove directory function
					} elseif (is_dir($file)) {
						rmdir($file);
					}
				}
			}					

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('extension/modification', 'token=' . $this->session->data['token'] . $url, 'SSL'));
		}

		$this->getList();
	}

	public function enable() {
		$this->load->language('extension/modification');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/modification');

		if (isset($this->request->get['modification_id']) && $this->validate()) {
			$this->model_extension_modification->enableModification($this->request->get['modification_id']);

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('extension/modification', 'token=' . $this->session->data['token'] . $url, 'SSL'));
		}

		$this->getList();
	}

	public function disable() {
		$this->load->language('extension/modification');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/modification');

		if (isset($this->request->get['modification_id']) && $this->validate()) {
			$this->model_extension_modification->disableModification($this->request->get['modification_id']);

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('extension/modification', 'token=' . $this->session->data['token'] . $url, 'SSL'));
		}

		$this->getList();
	}

	public function clearlog() {
		$this->load->language('extension/modification');

		if ($this->validate()) {
			$handle = fopen(DIR_LOGS . 'ocmod.log', 'w+');

			fclose($handle);

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('extension/modification', 'token=' . $this->session->data['token'] . $url, 'SSL'));
		}

		$this->getList();
	}

	protected function getList() {
		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'name';
		}

		if (isset($this->request->get['order'])) {
			$order = $this->request->get['order'];
		} else {
			$order = 'ASC';
		}

		if (isset($this->request->get['page'])) {
			$page = $this->request->get['page'];
		} else {
			$page = 1;
		}

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}
		
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], 'SSL')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/modification', 'token=' . $this->session->data['token'], 'SSL')
		);
		
		$data['refresh'] = $this->url->link('extension/modification/refresh', 'token=' . $this->session->data['token'] . $url, 'SSL');
		$data['clear'] = $this->url->link('extension/modification/clear', 'token=' . $this->session->data['token'] . $url, 'SSL');
		$data['delete'] = $this->url->link('extension/modification/delete', 'token=' . $this->session->data['token'] . $url, 'SSL');

		$data['modifications'] = array();

		$filter_data = array(
			'sort'  => $sort,
			'order' => $order,
			'start' => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit' => $this->config->get('config_limit_admin')
		);

		$modification_total = $this->model_extension_modification->getTotalModifications();

		$results = $this->model_extension_modification->getModifications($filter_data);

		foreach ($results as $result) {
			$data['modifications'][] = array(
				'modification_id' => $result['modification_id'],
				'name'            => $result['name'],
				'author'          => $result['author'],
				'version'         => $result['version'],
				'status'          => $result['status'] ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
				'date_added'      => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
				'link'            => $result['link'],
				'enable'          => $this->url->link('extension/modification/enable', 'token=' . $this->session->data['token'] . '&modification_id=' . $result['modification_id'], 'SSL'),
				'disable'         => $this->url->link('extension/modification/disable', 'token=' . $this->session->data['token'] . '&modification_id=' . $result['modification_id'], 'SSL'),
				'enabled'         => $result['status'],
			);
		}

		$data['heading_title'] = $this->language->get('heading_title');
		
		$data['text_list'] = $this->language->get('text_list');
		$data['text_no_results'] = $this->language->get('text_no_results');
		$data['text_confirm'] = $this->language->get('text_confirm');
		$data['text_refresh'] = $this->language->get('text_refresh');

		$data['column_name'] = $this->language->get('column_name');
		$data['column_author'] = $this->language->get('column_author');
		$data['column_version'] = $this->language->get('column_version');
		$data['column_status'] = $this->language->get('column_status');
		$data['column_date_added'] = $this->language->get('column_date_added');
		$data['column_action'] = $this->language->get('column_action');

		$data['button_refresh'] = $this->language->get('button_refresh');
		$data['button_clear'] = $this->language->get('button_clear');
		$data['button_delete'] = $this->language->get('button_delete');
		$data['button_link'] = $this->language->get('button_link');
		$data['button_enable'] = $this->language->get('button_enable');
		$data['button_disable'] = $this->language->get('button_disable');

		$data['tab_general'] = $this->language->get('tab_general');
		$data['tab_log'] = $this->language->get('tab_log');

		$data['token'] = $this->session->data['token'];

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		if (isset($this->request->post['selected'])) {
			$data['selected'] = (array)$this->request->post['selected'];
		} else {
			$data['selected'] = array();
		}

		$url = '';

		if ($order == 'ASC') {
			$url .= '&order=DESC';
		} else {
			$url .= '&order=ASC';
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['sort_name'] = $this->url->link('extension/modification', 'token=' . $this->session->data['token'] . '&sort=name' . $url, 'SSL');
		$data['sort_author'] = $this->url->link('extension/modification', 'token=' . $this->session->data['token'] . '&sort=author' . $url, 'SSL');
		$data['sort_version'] = $this->url->link('extension/version', 'token=' . $this->session->data['token'] . '&sort=author' . $url, 'SSL');
		$data['sort_status'] = $this->url->link('extension/modification', 'token=' . $this->session->data['token'] . '&sort=status' . $url, 'SSL');
		$data['sort_date_added'] = $this->url->link('extension/modification', 'token=' . $this->session->data['token'] . '&sort=date_added' . $url, 'SSL');

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		$pagination = new Pagination();
		$pagination->total = $modification_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('extension/modification', 'token=' . $this->session->data['token'] . $url . '&page={page}', 'SSL');

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($modification_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($modification_total - $this->config->get('config_limit_admin'))) ? $modification_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $modification_total, ceil($modification_total / $this->config->get('config_limit_admin')));

		$data['sort'] = $sort;
		$data['order'] = $order;

		// Log
		$file = DIR_LOGS . 'ocmod.log';

		if (file_exists($file)) {
			$data['log'] = htmlentities(file_get_contents($file, FILE_USE_INCLUDE_PATH, null));
		} else {
			$data['log'] = '';
		}

		$data['clear_log'] = $this->url->link('extension/modification/clearlog', 'token=' . $this->session->data['token'], 'SSL');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/modification.tpl', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/modification')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}
