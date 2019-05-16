<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Table_builder
{
	protected $ci;

	// RAW QUERY DATA
	protected $raw_data;

	// PAGINATED QUERY DATA
	protected $table_data;

	// TOTAL ROWS
	protected $total_rows;

	// COLUMNS FROM RAW DATA
	protected $data_columns;

	// USER DEFINED TABLE HEADINGS
	protected $table_headings;

	// USER DEFINED SEARCH FIELD
	protected $search_fields;

	// PAGINATION CLAS 
	protected $pagination, $pagination_config;

	public function __construct()
	{
        $this->ci =& get_instance();
	}

	public function setData($data, $custom_pagination_config = array())
	{
		// VERIFY DATA IS CORRECT
		if ( gettype($data) !== 'object' || $data instanceof CI_DB_mysqli_driver === false ) {
			throw new Exception('The table data must be an instance of CI_DB_mysqli_driver', 1);
		}

		// CLONE DATA FOR REUSE
		$this->raw_data = clone $data;
		$this->data_columns = clone $data;
		$this->table_data = clone $data;

		// GET TOTAL ROWS
		$this->total_rows = $this->raw_data->get()->num_rows();

		// READ A SAMPLE ROW TO GET COLUMN HEDAINGS
		$this->data_columns = $this->data_columns->get()->list_fields();

		// SETUP PAGINATION VARS
		$this->pagination_config = $this->setPaginationConfig(array_merge(array(
			'total_rows' => $this->total_rows
		), $custom_pagination_config));

		// CHECK BASE URL IS SET
		if ( ! isset($this->pagination_config['base_url']) ) {
			throw new Exception('base_url not defined in pagination config', 1);
		}

		// INIT PAGINATON
		$this->ci->load->library('pagination');
		$this->ci->pagination->initialize($this->pagination_config);

		// GET PAGER LINKS
		$this->pagination = $this->ci->pagination->create_links();

		// QUERY RECORDS FOR PAGINATED VIEW
		$this->table_data = $this->table_data->limit($this->pagination_config['per_page'])->offset($this->ci->input->get('offset'))->get()->result_array();
	}

	/**
	 * SET TABLE HEADING
	 *  
	 * @param  string  $data_column - Data index from x`
	 * @param  array   $params - Header row settings
	 * @param  boolean $skip_validaton - Should we check the data_colum exists in the data?
	 */
	public function setHeading($data_column, $params = array(), $skip_validaton = false)
	{
		if ( $this->total_rows > 0 && ! $skip_validaton && ! in_array($data_column, $this->data_columns) ) {
			throw new Exception('The data column "' . $data_column . '" does not exist in the data set', 1);
		}

		$this->table_headings[] = array(
			'data_column' => $data_column,
			
			'label' => isset($params['label']) ? $params['label'] : $data_column,
			'width' => isset($params['width']) ? $params['width'] : 'auto',
			'callback' => isset($params['callback']) ? $params['callback'] : false,
		);

		return $this;
	}


	public function setSearchField($search_field)
	{
		$this->search_fields[] = $search_field;

		return $this;
	}


	public function generate()
	{
		if ( empty($this->table_headings) ) {
			throw new Exception('No table headings defined', 1);
		}

		// LOAD LIBRARY
		$this->ci->load->library('table');
		$this->ci->table->clear();

		// SET TABLE CONFIG
		$this->ci->table->set_template(array(
			'table_open'		=> '<table class="table table-borderless table-striped mb-0">',
			'cell_start'		=> '<td class="align-middle">',
			'cell_end'			=> '</td>',
 		));

		// ADD HEADINGS
		$headings = array();
		foreach ( $this->table_headings as $heading ) {
			$headings[] = array( 'data' => ucfirst($heading['label']), 'width' => $heading['width']);
		}

		// SET THE HEADINGS TO THE CI CLASS
		$this->ci->table->set_heading(...$headings);

		// CHECK TOTAL ROWS FOR DATA
		if ( $this->total_rows === 0 ) {
			$this->ci->table->add_row(array( 'data' => 'No results found', 'colspan' => count($this->table_headings) ));
		} else {
			foreach ( $this->table_data as $unformatted_row ) {

				$row = array();

				foreach ( $this->table_headings as $heading ) {

					$callback = $heading['callback'];
					$raw_cell_value = isset($unformatted_row[$heading['data_column']]) ? $unformatted_row[$heading['data_column']] : false;

					if ( is_callable($callback) ) {
						$row[] = $callback($raw_cell_value, ( isset($unformatted_row['id']) ? $unformatted_row['id'] : false ) );
					} else {
						$row[] = $raw_cell_value;
					}
				}

				$this->ci->table->add_row(...$row);
			}
		}


		// OUTPUT
		return $this->search_form() . $this->ci->table->generate() . '<div class="card-footer">' . $this->pagination . '</div>';
	}

	protected function setPaginationConfig($custom_configuration = array())
	{

		// BOOTSTRAP 4 STYLING
		$defaults['full_tag_open'] = '<ul class="pagination">';
		$defaults['full_tag_close'] = '</ul>';
		$defaults['attributes'] = ['class' => 'page-link'];
		$defaults['first_link'] = false;
		$defaults['last_link'] = false;
		$defaults['first_tag_open'] = '<li class="page-item">';
		$defaults['first_tag_close'] = '</li>';
		$defaults['prev_link'] = '&laquo';
		$defaults['prev_tag_open'] = '<li class="page-item">';
		$defaults['prev_tag_close'] = '</li>';
		$defaults['next_link'] = '&raquo';
		$defaults['next_tag_open'] = '<li class="page-item">';
		$defaults['next_tag_close'] = '</li>';
		$defaults['last_tag_open'] = '<li class="page-item">';
		$defaults['last_tag_close'] = '</li>';
		$defaults['cur_tag_open'] = '<li class="page-item active"><a href="javascript:;" class="page-link">';
		$defaults['cur_tag_close'] = '<span class="sr-only">(current)</span></a></li>';
		$defaults['num_tag_open'] = '<li class="page-item">';
		$defaults['num_tag_close'] = '</li>';

		// PAGER CONFIG
		$defaults['page_query_string'] = TRUE;
		$defaults['query_string_segment'] = 'offset';
		$defaults['reuse_query_string'] = TRUE;
		$defaults['base_url'] = NULL;
		$defaults['per_page'] = 2;

		$config = array_merge($defaults, $custom_configuration);

		if ( ! empty($custom_configuration) ) {
			return $this->pagination_config = $config;
		}

		return $config;
	}

	protected function search_form()
	{
		$html = '';

		if ( ! empty($this->search_fields) ) {
			$html = '<div class="card-header card-header-search">';
				$html .= form_open(null, array( 'method' => 'GET' ));
					$html .= '<div class="d-flex align-items-center justify-content-start">';

					foreach ( $this->search_fields as $field ) {
						$html .= '<div class="mr-3">';
							$html .= $this->ci->form_builder->input($field);
						$html .= '</div>';
					}

					$html .= $this->ci->form_builder->submit(array(
						'name' => 'search',
						'value' => 'Search'
					));

					$html .= '</div>';
				$html .= form_close();
			$html .= '</div>';
		}

		return $html;
	}

}