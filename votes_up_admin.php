<?php





// mt_toplevel_page() displays the page content for the custom  menu
function mt_toplevel_page()
{
    //заполнение полей из БД
    global $wpdb;
    $tbl_votes_up = $wpdb->prefix . "votes_up";
    $row_votes_up = $wpdb->get_row("SELECT * FROM $tbl_votes_up where id = 1", ARRAY_A);
    $my_cred_enabled = $row_votes_up['choice'];
    if ($my_cred_enabled == 1) {
        if (isset($_POST['voting_up'])) {
            $votes_up_cred = $_POST['voting_up'];
        } else {
            $votes_up_cred = $row_votes_up['votes_up_cred'];
        }
    } else {
        $votes_up_cred = "0.0";
    }

    $pay_message = $row_votes_up['Pay_message'];
    if ($pay_message == 1) {
        if (isset($_POST['pay_vote_cred'])) {
            $pay_vote_cred = $_POST['pay_vote_cred'];
        } else {
            $pay_vote_cred = $row_votes_up['pay_vote_cred'];
            $pay_reply = $row_votes_up['Pay_reply'];
        }
    } else {
        $pay_vote_cred = "0.0";
    }

    //Получение данных формы и запись в БД
    if (isset($_POST['submit'])) {
        votes_up_set_data();
        $row_votes_up = $wpdb->get_row("SELECT * FROM $tbl_votes_up where id = 1", ARRAY_A);
        $my_cred_enabled = $row_votes_up['choice'];
        $votes_up_cred = $row_votes_up['votes_up_cred'];
        $pay_message = $row_votes_up['Pay_message'];
        $pay_vote_cred = $row_votes_up['pay_vote_cred'];
        if ($pay_message == 1) {
            $pay_reply = $row_votes_up['Pay_reply'];
        } else {
            $pay_reply = 0;
        }
    }




include("votes_up_admin.phtml");

}

?>
