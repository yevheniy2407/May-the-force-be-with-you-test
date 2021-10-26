SELECT
    object_id,
    sum( CASE WHEN action = 'buy' THEN amount ELSE 0 END ) spend_money_on_boosterpack,
    sum( CASE WHEN action = 'won_likes' THEN amount ELSE 0 END ) won_likes_from_boosterpack,
    DATE_FORMAT( time_created, '%Y-%m-%d %H' ) date_hour
FROM
    analytics
WHERE
        object = 'boosterpack'
        AND time_created > NOW() - INTERVAL 30 DAY
GROUP BY
    object_id,
    DATE_FORMAT( time_created, '%Y-%m-%d %H' )
ORDER BY
    DATE_FORMAT( time_created, '%Y-%m-%d %H' ) DESC, object_id DESC;




SELECT
    personaname,
    wallet_total_refilled,
    sum( analytics.amount ) total_likes_won,
    wallet_balance,
    likes_balance
FROM
    user
        LEFT JOIN analytics ON USER.id = analytics.user_id
        AND analytics.action = 'won_likes'
GROUP BY
    user.id;
