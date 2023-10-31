<?php

function controller_search($act, $data) {
    if ($act == 'plots') return Plot::plots_fetch($data);
    if ($act == 'users') return User::users_fetch($data);
    return '';
}
