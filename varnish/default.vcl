# This is my VCL file for Varnish 4.0.2 & Wordpress 4.0

vcl 4.0;

# Imports
import std;

# Default backend definition. Set this to point to your content server.
backend default {
  .host = "wordpress";
  .probe = {
    .url = "/";
    .timeout = 1s;
    .interval = 3s;
    .window = 5;
    .threshold = 3;
  }
}

sub vcl_recv {
  ###
  ### Do not Cache: special cases
  ###

  set req.http.grace = "none";

  ### Do not Authorized requests.
  if (req.http.Authorization) {
    return(pass); // DO NOT CACHE
  }

  ### Pass any requests with the "If-None-Match" header directly.
  if (req.http.If-None-Match) {
    return(pass); // DO NOT CACHE
  }

  ### Do not cache AJAX requests.
  if (req.http.X-Requested-With == "XMLHttpRequest") {
    return(pass); // DO NOT CACHE
  }

  ### Only cache GET or HEAD requests. This makes sure the POST (and OPTIONS) requests are always passed.
  if (req.method != "GET" && req.method != "HEAD") {
    return (pass); // DO NOT CACHE
  }

  ### 
  ### Request URL
  ###

  # Wordpress: disable caching for some parts of the backend (mostly admin stuff)
  # and WP search results.
  if (
    req.url ~ "^/wp-(login|admin)" || req.url ~ "/wp-cron.php" || req.url ~ "preview=true" || req.url ~ "xmlrpc.php" || req.url ~ "\?s="
  ) {
    # do not use the cache
    return(pass); // DO NOT CACHE
  }

  ### 
  ### http header Cookie
  ###   Remove some cookies (if found).
  ###
  # https://www.varnish-cache.org/docs/4.0/users-guide/increasing-your-hitrate.html#cookies

  # Unset the header for static files
  if (req.url ~ "\.(css|flv|gif|htm|html|ico|jpeg|jpg|js|mp3|mp4|pdf|png|swf|tif|tiff|xml)(\?.*|)$") {
    unset req.http.Cookie;
  }

  if (req.http.cookie) {
    # Google Analytics
    set req.http.Cookie = regsuball( req.http.Cookie, "(^|;\s*)(__utm[a-z]+)=([^;]*)", "");
    set req.http.Cookie = regsuball( req.http.Cookie, "(^|;\s*)(_ga)=([^;]*)", "");

    # Quant Capital
    set req.http.Cookie = regsuball( req.http.Cookie, "(^|;\s*)(__qc[a-z]+)=([^;]*)", "");

    # __gad __gads
    set req.http.Cookie = regsuball( req.http.Cookie, "(^|;\s*)(__gad[a-z]+)=([^;]*)", "");

    # Google Cookie consent (client javascript cookie)
    set req.http.Cookie = regsuball( req.http.Cookie, "(^|;\s*)(displayCookieConsent)=([^;]*)", "");

    # Other known Cookies: remove them (if found).
    set req.http.Cookie = regsuball( req.http.Cookie, "(^|;\s*)(__CT_Data)=([^;]*)", "");
    set req.http.Cookie = regsuball( req.http.Cookie, "(^|;\s*)(WRIgnore|WRUID)=([^;]*)", "");

    # PostAction: Remove (once and if found) a ";" prefix followed by 0..n whitespaces.
    # INFO \s* = 0..n whitespace characters
    set req.http.Cookie = regsub( req.http.Cookie, "^;\s*", "" );

    # PostAction: Unset the header if it is empty or 0..n whitespaces.
    if ( req.http.cookie ~ "^\s*$" ) {
      unset req.http.Cookie;
    }
  }

  ###
  ### Normalize the Accept-Language header
  ### We do not need a cache for each language-country combination! Just keep en-* and nl-* for future use.
  ### https://www.varnish-cache.org/docs/4.0/users-guide/increasing-your-hitrate.html#http-vary
  if (req.http.Accept-Language) {
    if (req.http.Accept-Language ~ "^en") {
      set req.http.Accept-Language = "en";
    } elsif (req.http.Accept-Language ~ "^nl") {
      set req.http.Accept-Language = "nl";
    } else {
      # Unknown language. Set it to English.
      set req.http.Accept-Language = "en";
    }
  }

  ### Varnish v4: vcl_recv must now return hash instead of lookup
  return(hash);
}

sub vcl_hit {
  if (obj.ttl >= 0s) {
    # normal hit
    return (deliver);
  }

  # We have no fresh fish. Lets look at the stale ones.
  if (std.healthy(req.backend_hint)) {
    # Backend is healthy. Limit age to 10s.
    if (obj.ttl + 10s > 0s) {
      set req.http.grace = "normal(limited)";
      return (deliver);
    } else {
      # No candidate for grace. Fetch a fresh object.
      return(fetch);
    }
  } else {
    # backend is sick - use full grace
    if (obj.ttl + obj.grace > 0s) {
      set req.http.grace = "full";
      return (deliver);
    } else {
      # no graced object.
      return (fetch);
    }
  }
}

sub vcl_deliver {
  set resp.http.grace = req.http.grace;
  if (obj.hits > 0) {
    set resp.http.X-Cache = "HIT";
  } else {
    set resp.http.X-Cache = "MISS";
  }
}

sub vcl_backend_response {
  set beresp.grace = 1h;
}
