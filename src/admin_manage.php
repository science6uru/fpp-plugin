<?php
class PhotosAdminTable extends WP_List_Table
{
	private $station_id;
	private $basedir;
	private $nonce_field;
	private $current_status;
	private $super_admin;
	
	public function __construct($station_id)
	{
		$this->nonce_field = wp_nonce_field('fpp_photo_manage');
		$this->basedir = fpp_photos_uri($station_id);
		$this->station_id = $station_id;
		$this->current_status = $_GET['status'] ?? 'unreviewed';
		$this->super_admin = array_key_exists('fpp-admin', $_GET);
		
		parent::__construct([
			'singular' => 'photo', // singular name of the listed records
			'plural'   => 'photos', // plural name of the listed records
			'ajax'     => true      // does this table support ajax?
		]);
		$this->_column_headers = array( 
			$this->get_columns(),		// columns
			array(),			// hidden
			$this->get_sortable_columns(),	// sortable
		);
	}
	public function get_columns()
	{
		$columns = [
			'cb'      => '<input type="checkbox" />', // Checkbox column
			'thumbnail'    => 'Image',
			'status'     => 'Status',
			'ip' => 'IP Address',
			'captcha_score' => 'Captcha Score',
			'captcha_mode' => 'Captcha Mode',
			'created' => 'Uploaded',
			'taken' => 'Taken',
			'metadata' => "Metadata",
		];
		
		if ($this->super_admin) {
			$columns['operations'] = 'Operations';
		}
		// only when filtering by rejected status or super admin
		if ($this->current_status === 'rejected' || $this->super_admin) {
			$columns['delete'] = 'Delete';
		}
		
		return $columns;
	}
	public function get_sortable_columns()
	{
		$sortable_columns = [
			'created' => ['created', false], // sortable true, default order ascending
			'status'  => ['status', false],
			'taken'  => ['taken', false],
			'ip'  => ['ip', false],
			'captcha_score'  => ['captcha_score', false],
			'captcha_mode'  => ['captcha_mode', false],
		];
		return $sortable_columns;
	}
	public function column_default($item, $column_name)
	{
		switch ($column_name) {
			case 'thumbnail':
				$thumbname = $this->basedir . "/" . $item['thumb_200'];
				$filename = $this->basedir . "/" . $item['file_name'];
				return "<a href='$filename' target='_blank'><img src='$thumbname'/></a>";
			case 'status':
				$status = $item["status"];
				$id = $item["id"];
				$nonce = $this->nonce_field;
				$action = "<input name='action' hidden value='fpp_photo_update_status'/>";
				$id_field = "<input name='fpp_photo_id' hidden value='$id'/>";
				$buttons = "";
				
				$buttons .= "<button class='fpp_photo_approve_btn' ". disabled("approved", $status, false) ." name='fpp_photo_status' value='approved'>Approve</button>";
				$buttons .= "<button class='fpp_photo_reject_btn' ". disabled("rejected", $status, false) ." name='fpp_photo_status' value='rejected'>Reject</button>";
				return "<form method='post'>$nonce $action $id_field $buttons</form>";
			case 'delete':
				$id = $item["id"];
				$nonce = $this->nonce_field;
				$action = "<input name='action' hidden value='fpp_photo_delete'/>";
				$id_field = "<input name='fpp_photo_id' hidden value='$id'/>";
				return "<form method='post'>$nonce $action $id_field <button class='button button-secondary' type='submit' onclick='return confirm(\"Are you sure you want to delete this photo?\")'>Delete</button></form>";
			case 'ip':
			case 'captcha_score':
			case 'captcha_mode':
			case 'created':
			case 'taken':
				return $item[$column_name];
			case 'cb':
				return "<input type='checkbox'/>";
			case 'metadata':
				return "<textarea readonly style='height:200px;'>".($item[$column_name]?json_encode(json_decode($item[$column_name]), JSON_PRETTY_PRINT) : "")."</textarea>";
			case 'operations':
				$id = $item["id"];
				$nonce = $this->nonce_field;
				$regen_disabled = $item['image_2000'] ? "disabled" : "";
				$meta_disabled = $item['metadata'] ? "disabled" : "";
				$regen_image = "<button name='action' $regen_disabled class='button button-secondary' value='fpp_regen_image'>Create Image</button>";
				$capture_meta = "<button name='action' $meta_disabled class='button button-secondary' value='fpp_collect_meta'>Capture Meta</button>";
				$id_field = "<input name='fpp_photo_id' hidden value='$id'/>";
				return "<form method='post'>$nonce $regen_image $capture_meta $id_field </form>";
			default:
				return print_r($item, true); // Show the whole array for troubleshooting purposes
		}
	}
	function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="element[]" value="%s" />',
			$item['id'] // Replace 'id' with the unique identifier of your data item
		);
	}
	function extra_tablenav( $which ) {
		if ( 'top' === $which || true) {
			?>
			<div class="alignleft bulkactions">
				<?php
				// Example: A dropdown for filtering by a custom 'status' meta key
				$status = $_GET['status'] ?? '';
				?>
				<form method="get">
					<input name="page" hidden value='<?= $_REQUEST["page"] ?>' style="display:none;"/>
					<select name="status" id="status" onchange="submit()">
						<option value="unreviewed">Not Yet Reviewed</option>
						<option value="all" <?php selected( $status, 'all' ); ?>>All Photos</option>
						<option value="approved" <?php selected( $status, 'approved' ); ?>>Approved</option>
						<option value="rejected" <?php selected( $status, 'rejected' ); ?>>Rejected</option>
					</select>
				<?php
				// The "Filter" button is required for core functionality to work correctly with custom filters
				submit_button( 'Filter', 'secondary', 'filter_action', false );
				?>
				</form>

			</div>
			<?php
		}
	}
	public function prepare_items()
	{

		global $wpdb, $fpp_photos;
		$this->_column_headers = $this->get_column_info();
		$orderby = array_key_exists("orderby", $_GET) ? $_GET["orderby"] : "created";
		$order = array_key_exists("order", $_GET) ? $_GET["order"] : "desc";
		$status = array_key_exists("status", $_GET) ? $_GET["status"] : "unreviewed";
		$status_where = '';
		if(in_array($status, ['unreviewed', 'approved', 'rejected'])) {
			$status_where = "and status = '{$status}'";
		}
		if (!in_array($order, ["asc", "desc", "ASC", "DESC"])) {
			$order = "desc";
		}
		if (!array_key_exists($orderby, $this->get_sortable_columns())) {
			$orderby = "created";
		}
		$where_clause = "station_id = {$this->station_id} {$status_where}";

		$row_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$fpp_photos} where {$where_clause}" );

		$per_page     = 10;
		$current_page = $this->get_pagenum();
		$total_items  = $row_count;
		$this->set_pagination_args([
			'total_items' => $total_items,
			'per_page'    => $per_page,
		]);
		$offset = ($current_page - 1) * $per_page;

	    $this->items = $wpdb->get_results("SELECT * FROM {$fpp_photos} where {$where_clause} ORDER BY {$orderby} {$order} LIMIT {$offset}, {$per_page}", ARRAY_A);
	}
}
$photos_table = new PhotosAdminTable($station_id);
$photos_table->prepare_items();
?>
<h1><?= $station_name ?></h1>
<div class="wrap">
	<div class="fpp-tabber">
		<div class="fpp-tab-group">
			<a class="fpp-tab" href="#manage-photos" id="tab-manage-photos">Manage Photos</a>
			<a class="fpp-tab" href="#upload-photo" id="tab-upload-photo">Upload Photo</a>
			<a class="fpp-tab" href="#view-timelapse" id="tab-view-timelapse">View Timelapse</a>
		</div>
		<div class="fpp-tab-views">
			<div class="fpp-tab-view" id="view-manage-photos">
				<div class="fpp-photos-table">
					<?php $photos_table->display(); ?>
				</div>
			</div>
			<div class="fpp-tab-view" id="view-upload-photo">
				<?php echo do_shortcode("[fpp_upload station={$station_slug}]"); ?>
			</div>
			<div class="fpp-tab-view" id="view-view-timelapse">
				<div style="width:80%;margin:auto;">
					<?php echo do_shortcode("[fpp_carousel station={$station_slug}]"); ?>
				</div>
			</div>
		</div>
	</div>
</div>