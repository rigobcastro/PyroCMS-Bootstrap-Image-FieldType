<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * PyroStreams Bootstrap Image Field Type
 *
 * @package		PyroCMS\Core\Modules\Streams Core\Field Types
 * @author		Rigo B Castro
 * @author		Parse19
 * @copyright           Copyright (c) 2011 - 2013
 * @license		https://github.com/rigobcastro/PyroCMS-Bootstrap-Image-FieldType/blob/master/LICENSE
 * @link		https://github.com/rigobcastro/PyroCMS-Bootstrap-Image-FieldType
 */
class Field_bootstrap_image {

    public $field_type_slug = 'bootstrap_image';
    // Files are saved as 15 character strings.
    public $db_col_type = 'char';
    public $col_constraint = 15;
    public $custom_parameters = array('folder', 'resize_width', 'resize_height', 'keep_ratio', 'allowed_types');
    public $version = '1.0.1';
    public $author = array('name' => 'Rigo B Castro', 'url' => 'http://rigobcastro.com');
    public $input_is_file = true;

    // --------------------------------------------------------------------------

    public function __construct()
    {
        get_instance()->load->library('image_lib');
    }

    // --------------------------------------------------------------------------

    /**
     * Output form input
     *
     * @param	array
     * @param	array
     * @return	string
     */
    public function form_output($params)
    {
        $this->CI->load->config('files/files');
        $current_file = null;

        // Get the file
        if ($params['value'])
        {
            $current_file = $this->CI->db
                ->where('id', $params['value'])
                ->limit(1)
                ->get('files')
                ->row();
        }

        $this->CI->type->add_js('bootstrap_image', 'bootstrap-fileupload.min.js');
        $this->CI->type->add_css('bootstrap_image', 'bootstrap-fileupload.min.css');
     

        $view = $this->CI->type->load_view('bootstrap_image', 'input', array(
            'params' => $params,
            'current_file' => $current_file,
            'width' => !empty($params['custom']['resize_width']) ? $params['custom']['resize_width'] : 200,
            'height' => !empty($params['custom']['resize_height']) ? $params['custom']['resize_height'] : 150,
            'accept_input_html5' => 'image/*'
        ));

        return $view;
    }

    // --------------------------------------------------------------------------

    /**
     * Process before saving to database
     *
     * @access	public
     * @param	array
     * @param	obj
     * @return	string
     */
    public function pre_save($input, $field, $stream, $row_id, $form_data)
    {
        // If we do not have a file that is being submitted. If we do not,
        // it could be the case that we already have one, in which case just
        // return the numeric file record value.
        if (!isset($_FILES[$field->field_slug . '_file']['name']) or !$_FILES[$field->field_slug . '_file']['name'])
        {
            // allow dummy as a reset
            if (isset($form_data[$field->field_slug]) and $form_data[$field->field_slug])
            {
                return $form_data[$field->field_slug];
            }
            else
            {
                return null;
            }
        }

        $this->CI->load->library('files/files');

        // Resize options
        $resize_width = (isset($field->field_data['resize_width'])) ? $field->field_data['resize_width'] : null;
        $resize_height = (isset($field->field_data['resize_height'])) ? $field->field_data['resize_height'] : null;
        $keep_ratio = (isset($field->field_data['keep_ratio']) and $field->field_data['keep_ratio'] == 'yes') ? true : false;

        $return = Files::upload($field->field_data['folder'], null, $field->field_slug . '_file', $resize_width, $resize_height, $keep_ratio, $allowed_types);

        if (!$return['status'])
        {
            $this->CI->session->set_flashdata('notice', $return['message']);
            return null;
        }
        else
        {
            // Return the ID of the file DB entry
            return $return['data']['id'];
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Pre Output
     *
     * @access	public
     * @param	array
     * @return	string
     */
    public function pre_output($input, $params)
    {
        if (!$input or $input == 'dummy')
            return null;

        // Get image data
        $image = $this->CI->db->select('filename, alt_attribute, description, name')->where('id', $input)->get('files')->row();

        if (!$image)
            return null;

        // This defaults to 100px wide
        return '<img src="' . site_url('files/thumb/' . $input) . '" alt="' . $this->obvious_alt($image) . '" />';
    }

    // --------------------------------------------------------------------------

    /**
     * Process before outputting for the plugin
     *
     * This creates an array of data to be merged with the
     * tag array so relationship data can be called with
     * a {field.column} syntax
     *
     * @access	public
     * @param	string
     * @param	string
     * @param	array
     * @return	array
     */
    public function pre_output_plugin($input, $params)
    {
        if (!$input or $input == 'dummy')
            return null;

        $this->CI->load->library('files/files');

        $file = Files::get_file($input);

        if ($file['status'])
        {
            $image = $file['data'];

            // If we don't have a path variable, we must have an
            // older style image, so let's create a local file path.
            if (!$image->path)
            {
                $image_data['image'] = base_url($this->CI->config->item('files:path') . $image->filename);
            }
            else
            {
                $image_data['image'] = str_replace('{{ url:site }}', base_url(), $image->path);
            }

            // For <img> tags only
            $alt = $this->obvious_alt($image);

            $image_data['filename'] = $image->filename;
            $image_data['name'] = $image->name;
            $image_data['alt'] = $image->alt_attribute;
            $image_data['description'] = $image->description;
            $image_data['img'] = img(array('alt' => $alt, 'src' => $image_data['image']));
            $image_data['ext'] = $image->extension;
            $image_data['mimetype'] = $image->mimetype;
            $image_data['width'] = $image->width;
            $image_data['height'] = $image->height;
            $image_data['id'] = $image->id;
            $image_data['filesize'] = $image->filesize;
            $image_data['download_count'] = $image->download_count;
            $image_data['date_added'] = $image->date_added;
            $image_data['folder_id'] = $image->folder_id;
            $image_data['folder_name'] = $image->folder_name;
            $image_data['folder_slug'] = $image->folder_slug;
            $image_data['thumb'] = site_url('files/thumb/' . $input);
            $image_data['thumb_img'] = img(array('alt' => $alt, 'src' => site_url('files/thumb/' . $input)));

            return $image_data;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Choose a folder to upload to.
     *
     * @access	public
     * @param	[string - value]
     * @return	string
     */
    public function param_folder($value = null)
    {
        // Get the folders
        $this->CI->load->model('files/file_folders_m');

        $tree = $this->CI->file_folders_m->get_folders();

        $tree = (array) $tree;

        if (!$tree)
        {
            return '<em>' . lang('streams:bootstrap_image.need_folder') . '</em>';
        }

        $choices = array();

        foreach ($tree as $tree_item)
        {
            // We are doing this to be backwards compat
            // with PyroStreams 1.1 and below where
            // This is an array, not an object
            $tree_item = (object) $tree_item;

            $choices[$tree_item->id] = $tree_item->name;
        }

        return form_dropdown('folder', $choices, $value);
    }

    // --------------------------------------------------------------------------

    /**
     * Param Resize Width
     *
     * @access	public
     * @param	[string - value]
     * @return	string
     */
    public function param_resize_width($value = null)
    {
        return form_input('resize_width', $value);
    }

    // --------------------------------------------------------------------------

    /**
     * Param Resize Height
     *
     * @access	public
     * @param	[string - value]
     * @return	string
     */
    public function param_resize_height($value = null)
    {
        return form_input('resize_height', $value);
    }

    // --------------------------------------------------------------------------

    /**
     * Param Allowed Types
     *
     * @access	public
     * @param	[string - value]
     * @return	string
     */
    public function param_keep_ratio($value = null)
    {
        $choices = array('yes' => lang('global:yes'), 'no' => lang('global:no'));

        return array(
            'input' => form_dropdown('keep_ratio', $choices, $value),
            'instructions' => lang('streams:bootstrap_image.keep_ratio_instr'));
    }

    // --------------------------------------------------------------------------


    /**
     * Obvious alt attribute for <img> tags only
     *
     * @access	private
     * @param	obj
     * @return	string
     */
    private function obvious_alt($image)
    {
        if ($image->alt_attribute)
        {
            return $image->alt_attribute;
        }
        if ($image->description)
        {
            return $image->description;
        }
        return $image->name;
    }

}
