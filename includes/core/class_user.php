<?php

class User
{

    // GENERAL

    public static function user_info($d)
    {
        // vars
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $phone = isset($d['phone']) ? preg_replace('~\D+~', '', $d['phone']) : 0;
        // where
        if ($user_id) $where = "user_id='" . $user_id . "'";
        else if ($phone) $where = "phone='" . $phone . "'";
        else {
            return [
                'id' => 0,
                'access' => 0,
                'first_name' => '',
                'last_name' => '',
                'phone' => '',
                'email' => '',
                'plots' => '',
            ];
        }
        // info
        $q = DB::query("SELECT user_id, plot_id, first_name, last_name, phone, email, access FROM users WHERE " . $where . " LIMIT 1;") or die (DB::error());

        if ($row = DB::fetch_row($q)) {
            return [
                'id' => (int)$row['user_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'phone' => $row['phone'],
                'email' => $row['email'],
                'plots' => $row['plot_id'],
                'access' => (int)$row['access']
            ];
        } else {
            return [
                'id' => 0,
                'access' => 0,
                'first_name' => '',
                'last_name' => '',
                'phone' => '',
                'email' => '',
                'plots' => '',
            ];
        }
    }

    public static function users_list_plots($number)
    {
        // vars
        $items = [];
        // info
        $q = DB::query("SELECT user_id, plot_id, first_name, email, phone
            FROM users WHERE plot_id LIKE '%" . $number . "%' ORDER BY user_id;") or die (DB::error());
        while ($row = DB::fetch_row($q)) {
            $plot_ids = explode(',', $row['plot_id']);
            $val = false;
            foreach ($plot_ids as $plot_id) if ($plot_id == $number) $val = true;
            if ($val) $items[] = [
                'id' => (int)$row['user_id'],
                'first_name' => $row['first_name'],
                'email' => $row['email'],
                'phone_str' => phone_formatting($row['phone'])
            ];
        }
        // output
        return $items;
    }

    public static function users_fetch($data = [])
    {
        $info = User::users_list($data);
        HTML::assign('users', $info['items']);
        HTML::assign('search', $info['search']);
        return ['html' => HTML::fetch('./partials/users_table.html'), 'paginator' => $info['paginator']];
    }

    public static function users_list($d = [])
    {
        // vars
        $offset = isset($d['offset']) && is_numeric($d['offset']) ? $d['offset'] : 0;
        $searchData = isset($d['search']) ? json_decode(urldecode($d['search']), true) : null;
        $limit = 20;
        $items = [];
        // where
        $where = [];
        $search = ['email' => '', 'phone' => '', 'first_name' => ''];
        if ($searchData and 'array' == gettype($searchData)) {
            foreach ($searchData as $item) {
                $searchDataVal = isset($item['search']) && trim($item['search']) ? $item['search'] : '';
                if (isset($search[$item['column']])) $search[$item['column']] = $searchDataVal;
                if ($searchDataVal) $where[] = $item['column'] . " LIKE '%" . $searchDataVal . "%'";
            }
        }
        $search = array_values($search);
        $where = $where ? "WHERE " . implode(" AND ", $where) : "";
        // info
        $q = DB::query("SELECT user_id, plot_id, plot_id, first_name, last_name, email, phone, last_login
            FROM users " . $where . " ORDER BY user_id LIMIT " . $offset . ", " . $limit . ";") or die (DB::error());
        while ($row = DB::fetch_row($q)) {
            $items[] = [
                'user_id' => (int)$row['user_id'],
                'plot_id' => $row['plot_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'last_login' => $row['last_login'],
            ];
        }
        // paginator
        $q = DB::query("SELECT count(*) FROM users " . $where . ";");
        $count = ($row = DB::fetch_row($q)) ? $row['count(*)'] : 0;
        $url = 'users?';
        if (isset($d['search'])) $url .= '&search=' . urlencode($d['search']) . "&";
        paginator($count, $offset, $limit, $url, $paginator);
        // output
        return ['items' => $items, 'paginator' => $paginator, 'search' => $search];
    }

    public static function user_delete($d)
    {
        DB::query("DELETE FROM users WHERE user_id = " . $d['user_id']) or die (DB::error());
        return User::users_fetch($d);
    }

    public static function user_edit_window($d = [])
    {
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : null;
        HTML::assign('user', self::user_info(['user_id' => $user_id]));
        return ['html' => HTML::fetch('./partials/user_edit.html')];
    }

    public static function user_edit_update($d = [])
    {
        // vars
        $fd = $d['data'];
        $user_id = isset($fd['user_id']) && is_numeric($fd['user_id']) ? $fd['user_id'] : null;
        $isValidate = true;
        $validatedFields = ['first_name', 'last_name', 'email', 'phone'];
        $fd['plots'] = preg_replace('~[^0-9,]~', '', $fd['plots']);
        $fd['phone'] = preg_replace('~\D+~', '', $fd['phone']);
        foreach ($fd as $key => $val) {
            if (in_array($key, $validatedFields) && empty($val))
                $isValidate = false;
        }
        if ($isValidate) {
            $first_name = $fd['first_name'];
            $last_name = $fd['last_name'];
            $email = strtolower($fd['email']);
            $phone = $fd['phone'];
            $plots = explode(',', $fd['plots']);
            $plots = array_filter($plots);

            if ($user_id) {
                $setData = [];
                $setData[] = "first_name = '$first_name'";
                $setData[] = "last_name = '$last_name'";

                $setData[] = "email = '$email'";
                $setData[] = "phone = '$phone'";
                $setData[] = "plot_id = '" . implode(', ', $plots) . "'";
                $setData = implode(',', $setData);
                DB::query("UPDATE users SET $setData WHERE user_id = '$user_id'") or die(DB::error());
            } else {
                DB::query("INSERT INTO users(first_name, last_name, phone, email, plot_id) VALUES(
			  '$first_name','$last_name', '$phone', '$email','" . implode(', ', $plots) . "')") or die(DB::error());
            }
        }
        // output
        return self::users_fetch(['offset' => $d['offset'], 'search' => $d['search']]);
    }

}
