<?php
class sort_arrayby_date{
    public function sortResultByDate($arrayresult){
        function sortByDate($a, $b) {
            return $b['createddate'] - $a['createddate'];
        }
        usort($arrayresult, 'sortByDate');
        return $arrayresult;
    }
}
?>
