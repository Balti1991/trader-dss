<?php
include("trader-functions.php");
redirect_login_pf();
draw_trader_header('booty');
// Load the HTML_QuickForm module
global $db_hostname, $db_database, $db_user, $db_password;

$username = $_SESSION['username'];
$uid = $_SESSION['uid'];
$pf_id = $_SESSION['pfid'];
$pf_name = get_pf_name($pf_id);
$pf_working_date = get_pf_working_date($pf_id);
$pf_exch = get_pf_exch($pf_id);
$next_trade_day = next_trade_day($pf_working_date, $pf_exch);

try {
    $pdo = new PDO("pgsql:host=$db_hostname;dbname=$db_database", $db_user, $db_password);
} catch (PDOException $e) {
    die("ERROR: Cannot connect: " . $e->getMessage());
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function draw_table($pf_id, $pf_working_date, $pf_exch, $pf_name)
{
    global $pdo, $next_trade_day;
    print '<form action="' . $_SERVER['REQUEST_URI'] . '" method="post" name="cart" id="cart">';
    print '<table border="1" cellpadding="5" cellspacing="0" align="center">';
    print '<tr><td>Symb</td><td>Name</td><td>Comment</td><td>Volume</td><td>Buy Price</td><td>close</td><td>gain</td><td>Value</td>';
    if (isset($_POST['chart']))
    {
        print '<td>Chart</td></tr>';
    }
    print '</tr>';
    $query = "select * from trades where pfid = '$pf_id' order by symb;";
    foreach ($pdo->query($query) as $row)
    {
        $symb = $row['symb'];
        $trid = $row['trid'];
        $symb_name = get_symb_name($symb, $pf_exch);
        $price = $row['price'];
        $close = get_stock_close($symb, $pf_working_date, $pf_exch);
        $price_diff_pc = round(100 - (($price/$close)*100), 2);
        $volume = $row['volume'];
        $value = round($close*$volume, 2);
        if ($price_diff_pc == 0)
        {
            $colour = 'black';
        }
        elseif ($price_diff_pc > 0)
        {
            if ( $volume > 0)
            {
                $colour = 'green';
            }
            else
            {
                $colour = 'red';
            }
        }
        else
        {
            if ( $volume > 0)
            {
                $colour = 'red';
            }
            else
            {
                $colour = 'green';
            }
        }
        print "<tr><td><input type=\"checkbox\" name=\"mark[]\" value=\"$symb\"><font color=\"$colour\">$symb</font></td>\n";
        print "<td><font color=\"$colour\">$symb_name</font></td>\n";
        print "<td><textarea wrap=\"soft\" rows=\"1\" cols=\"50\" name=\"buy_comment_$trid\">" . $row['comment'] . '</textarea></td>';
        print "<td>" . $row['volume'] . '</td>';
        print "<td>$price</td>\n";
        print "<td>$close</td>\n";
        print "<td>$price_diff_pc %</td>\n";
        print "<td>$value</td>\n";
        if (isset($_POST['chart']))
        {
            print "<td><img SRC=\"/cgi-bin/chartstock.php?TickerSymbol=$symb&TimeRange=180&working_date=$pf_working_date&exch=$pf_exch&ChartSize=S&Volume=1&VGrid=1&HGrid=1&LogScale=0&ChartType=OHLC&Band=None&avgType1=SMA&movAvg1=10&avgType2=SMA&movAvg2=25&Indicator1=RSI&Indicator2=MACD&Indicator3=WilliamR&Indicator4=TRIX&Button1=Update%20Chart\" ALIGN=\"bottom\" BORDER=\"0\"></td>";
        }
        print "</tr>\n";
    }
    if (isset($_POST['chart']))
    {
        print "<tr><td colspan=\"10\"><input type=\"checkbox\" name=\"chart\" value=\"chart\" checked>Draw Charts</td>\n";
    }
    else
    {
        print "<tr><td colspan=\"10\"><input type=\"checkbox\" name=\"chart\" value=\"chart\">Draw Charts</td>\n";
    }
    print '<tr><td colspan="10"><input name="update" value="Update" type="submit"/></td></tr>';
    print '<tr><td colspan="10"><input name="sell" value="Sell" type="submit"/></td></tr>';
    print '<tr><td colspan="10"><input name="next_day" value="Go to the next day, '.$next_trade_day.'" type="submit"/></td></tr>';
    print '</table>';
    print '</form>';
}

// save any changes that have been typed into the form
update_trades($pf_id);

if(isset($_POST['delete']))
{
    if (isset($_POST['mark']))
    {
        $marked = $_POST['mark'];
        foreach ($marked as $symb)
        {
            del_from_cart('cart', $symb);
        }
    }
}
elseif(isset($_POST['next_day']))
{
    try 
    {
        $pdo->beginTransaction();
        // step portfolios on to the next day
        $query = "update portfolios set working_date = '$next_trade_day' where pfid = '$pf_id';";
        $pdo->exec($query);
        // copy the row forward for pf_summary
        $query = "select * from pf_summary where pfid = '$pf_id' and date = '$pf_working_date';";
        foreach ($pdo->query($query) as $row)
        {
            $cash_in_hand = $row['cash_in_hand'];
            $holdings = $row['holdings'];
        }
        // this isn't good enough, it must calculate all the current close prices and multiply them by volume and total them into holdings
        $query = "insert into pf_summary (pfid, date, cash_in_hand, holdings) values ('$pf_id', '$next_trade_day', '$cash_in_hand', '$holdings');";
        $pdo->exec($query);
        $pdo->commit();
    }
    catch (PDOException $e)
    {
        tr_warn('booty.php: next day failed' . $query . ':' . $e->getMessage());
    }
    $pf_working_date = $next_trade_day;
}
elseif(isset($_POST['sell']))
{
    if (isset($_POST['mark']))
    {
        $marked = $_POST['mark'];
        foreach ($marked as $symb)
        {
            sell_stock($symb, $_POST["buy_comment_$symb"], $_POST["buy_volume_$symb"]);
        }
    }
}

draw_table($pf_id, $pf_working_date, $pf_exch, $pf_name);
