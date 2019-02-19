# flow-log
WordPress plugin to log system events with message and optional post ID reference. Based on Drupal's watchdog module. 

Use it to log arbitrary system events / your own events along with a related post ID, variables etc.

Example usage: 

```
flow_log()->log(
  'your_plugin_name',
  'Plugin completed some action for user: %s',
  $username,
  $post_id
);
```

See code comments for more info. 
