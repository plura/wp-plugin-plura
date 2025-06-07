<?php

//https://wordpress.stackexchange.com/a/27498
//https://www.billerickson.net/code/wordpress-menu-as-select-dropdown/


class P_Walker_Nav_Menu_Dropdown extends Walker_Nav_Menu {

    // don't output children opening tag (`<ul>`)
    public function start_lvl(&$output, $depth = 0, $args = NULL){}

	// don't output children closing tag    
	public function end_lvl(&$output, $depth = 0, $args = NULL){}

	public function start_el(&$output, $data_object, $depth = 0, $args = NULL, $current_object_id = 0){

		// add spacing to the title based on the current depth
		$data_object->title = str_repeat("&nbsp;", $depth * 4) . $data_object->title;

		// call the prototype and replace the <li> tag
		// from the generated markup... 
		parent::start_el($output, $data_object, $depth, $args);


		$val = "<option value=\"" . $data_object->object_id . "\"";


		if( $data_object->current ) {

			$output = str_replace('<li', $val . ' selected', $output);

		} else {

			$output = str_replace('<li', $val, $output);

		}      

    }

    // replace closing </li> with the closing option tag
    public function end_el(&$output, $data_object, $depth = 0, $args = NULL) {

		$output .= "</option>\n";
    
    }

}

?>