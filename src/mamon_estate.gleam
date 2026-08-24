import envoy
import gleam/bit_array
import gleam/bytes_tree
import gleam/dynamic/decode
import gleam/erlang/process
import gleam/http/request.{type Request}
import gleam/http/response.{type Response}
import gleam/http.{Post}
import gleam/int
import gleam/list
import gleam/option
import gleam/otp/static_supervisor as supervisor
import gleam/result
import gleam/string
import gleam/uri
import mist.{type Connection, type ResponseData}
import pog

pub fn main() {
  let pool_name = process.new_name(prefix: "mamon_estate_db")
  let database_url =
    envoy.get("DATABASE_URL")
    |> result.unwrap("postgresql://mamon:mamon@localhost:5432/mamon_estate")
  let assert Ok(database_config) = pog.url_config(pool_name, database_url)
  let assert Ok(_) =
    supervisor.new(supervisor.OneForOne)
    |> supervisor.add(pog.supervised(database_config))
    |> supervisor.start

  let database = pog.named_connection(pool_name)
  let port = envoy.get("PORT") |> result.try(int.parse) |> result.unwrap(8080)
  let assert Ok(_) =
    fn(req: Request(Connection)) { router(req, database) }
    |> mist.new
    |> mist.bind("0.0.0.0")
    |> mist.port(port)
    |> mist.start
  process.sleep_forever()
}

fn router(req: Request(Connection), database: pog.Connection) -> Response(ResponseData) {
  case request.path_segments(req) {
    ["health"] -> text_response(200, "ok")
    ["htmx", "listings"] -> listings_fragment(database)
    ["htmx", "admin", "stats"] -> protected(req, fn() { admin_stats_fragment(database) })
    ["htmx", "admin", "listings"] -> protected(req, fn() { admin_listings(req, database) })
    ["htmx", "admin", "regions"] -> protected(req, fn() { admin_regions(req, database) })
    ["htmx", "admin", "settings", key] -> protected(req, fn() { admin_settings(req, database, key) })
    [] -> serve("index.html", "text/html; charset=utf-8")
    ["ilan.html"] -> serve("ilan.html", "text/html; charset=utf-8")
    ["ilan", _id] -> serve("ilan.html", "text/html; charset=utf-8")
    ["admin.html"] -> protected(req, fn() { serve("admin.html", "text/html; charset=utf-8") })
    ["satilik.html"] -> serve("satilik.html", "text/html; charset=utf-8")
    ["kiralik.html"] -> serve("kiralik.html", "text/html; charset=utf-8")
    ["bolgeler.html"] -> serve("bolgeler.html", "text/html; charset=utf-8")
    ["hakkimizda.html"] -> serve("hakkimizda.html", "text/html; charset=utf-8")
    ["iletisim.html"] -> serve("iletisim.html", "text/html; charset=utf-8")
    ["kvkk.html"] -> serve("kvkk.html", "text/html; charset=utf-8")
    ["gizlilik.html"] -> serve("gizlilik.html", "text/html; charset=utf-8")
    ["satilik"] -> serve("satilik.html", "text/html; charset=utf-8")
    ["kiralik"] -> serve("kiralik.html", "text/html; charset=utf-8")
    ["bolgeler"] -> serve("bolgeler.html", "text/html; charset=utf-8")
    ["hakkimizda"] -> serve("hakkimizda.html", "text/html; charset=utf-8")
    ["iletisim"] -> serve("iletisim.html", "text/html; charset=utf-8")
    ["kvkk"] -> serve("kvkk.html", "text/html; charset=utf-8")
    ["gizlilik"] -> serve("gizlilik.html", "text/html; charset=utf-8")
    ["admin"] -> protected(req, fn() { serve("admin.html", "text/html; charset=utf-8") })
    ["robots.txt"] -> serve("robots.txt", "text/plain; charset=utf-8")
    ["assets", ..rest] -> serve("assets/" <> string.join(rest, "/"), content_type(rest))
    _ -> text_response(404, "Not found")
  }
}

fn admin_settings(req: Request(Connection), database: pog.Connection, key: String) -> Response(ResponseData) {
  case req.method {
    Post -> case mist.read_body(req, 64_000) {
      Ok(body_req) -> {
        let value = body_req.body |> bit_array.to_string |> result.unwrap("")
        let saved = pog.query("insert into site_settings(key,value) values($1,to_jsonb($2::text)) on conflict(key) do update set value=excluded.value,updated_at=now()") |> pog.parameter(pog.text(key)) |> pog.parameter(pog.text(value)) |> pog.execute(database)
        case saved { Ok(_) -> html_response(200,"<span class='trans-ok'>Ayarlar PostgreSQL’e kaydedildi.</span>") Error(_) -> html_response(422,"<span class='htmx-error'>Ayarlar kaydedilemedi.</span>") }
      }
      Error(_) -> html_response(400,"<span class='htmx-error'>Geçersiz ayar verisi.</span>")
    }
    _ -> html_response(405,"<span class='htmx-error'>Desteklenmeyen işlem.</span>")
  }
}

fn protected(req: Request(Connection), handler: fn() -> Response(ResponseData)) -> Response(ResponseData) {
  let user = envoy.get("ADMIN_USER") |> result.unwrap("")
  let password = envoy.get("ADMIN_PASSWORD") |> result.unwrap("")
  let expected = "Basic " <> bit_array.base64_encode(bit_array.from_string(user <> ":" <> password), True)
  case user != "", password != "", request.get_header(req, "authorization") {
    True, True, Ok(value) if value == expected -> handler()
    _, _, _ -> response.new(401) |> response.set_header("www-authenticate", "Basic realm=\"Mamon Estate Yönetim\", charset=\"UTF-8\"") |> response.set_body(mist.Bytes(bytes_tree.from_string("Yönetici girişi gerekli")))
  }
}

fn admin_listings(req: Request(Connection), database: pog.Connection) -> Response(ResponseData) { case req.method { Post -> create_listing(req,database) _ -> admin_listings_fragment(database) } }

fn admin_listings_fragment(database: pog.Connection) -> Response(ResponseData) {
  let decoder = { use id <- decode.field(0, decode.int) use title <- decode.field(1, decode.string) use region <- decode.field(2, decode.string) use status <- decode.field(3, decode.string) decode.success(#(id,title,region,status)) }
  let data = pog.query("select l.id,l.title_tr,r.name,l.status::text from listings l join regions r on r.id=l.region_id order by l.created_at desc") |> pog.returning(decoder) |> pog.execute(database)
  let html = case data { Ok(rows) -> rows.rows |> list.map(fn(row) { let #(id,title,region,status)=row "<div class='mini-listing'><div><b>"<>title<>"</b><small>"<>region<>" · "<>status<>"</small></div><strong>MV-"<>int.to_string(id)<>"</strong></div>" }) |> string.join("") Error(_) -> "<p>İlanlar yüklenemedi.</p>" }
  html_response(200, html)
}

fn create_listing(req: Request(Connection), database: pog.Connection) -> Response(ResponseData) {
  case mist.read_body(req, 128_000) {
    Ok(body_req) -> {
      let fields = body_req.body |> bit_array.to_string |> result.try(uri.parse_query) |> result.unwrap([])
      let get = fn(key) { list.key_find(fields,key) |> result.unwrap("") }
      let sql = "insert into listings(region_id,title_tr,description_tr,property_type,sale_type,price_try,rooms,bathrooms,gross_area,cover_image,status,contract_type,contract_start,contract_end) select r.id,$1,$2,$3,$4,$5::numeric,$6,nullif($7,'')::smallint,nullif($8,'')::numeric,nullif($9,''),'published',$10::contract_kind,nullif($11,'')::date,nullif($12,'')::date from regions r where r.name=$13"
      let saved = pog.query(sql)
        |> pog.parameter(pog.text(get("title"))) |> pog.parameter(pog.text(get("description")))
        |> pog.parameter(pog.text(get("type"))) |> pog.parameter(pog.text(get("status")))
        |> pog.parameter(pog.text(get("price"))) |> pog.parameter(pog.text(get("rooms")))
        |> pog.parameter(pog.text(get("bath"))) |> pog.parameter(pog.text(get("area")))
        |> pog.parameter(pog.text(get("image"))) |> pog.parameter(pog.text(get("contractType")))
        |> pog.parameter(pog.text(get("startDate"))) |> pog.parameter(pog.text(get("endDate")))
        |> pog.parameter(pog.text(get("region"))) |> pog.execute(database)
      case saved { Ok(result) if result.count > 0 -> admin_listings_fragment(database) Ok(_) -> html_response(422,"<p class='htmx-error'>Önce geçerli bir bölge seçin.</p>") Error(_) -> html_response(422,"<p class='htmx-error'>İlan kaydedilemedi. Alanları kontrol edin.</p>") }
    }
    Error(_) -> html_response(400,"<p class='htmx-error'>Geçersiz form.</p>")
  }
}

fn admin_regions(req: Request(Connection), database: pog.Connection) -> Response(ResponseData) {
  case req.method {
    Post -> create_region(req, database)
    _ -> admin_regions_fragment(database)
  }
}

fn admin_regions_fragment(database: pog.Connection) -> Response(ResponseData) {
  let decoder = { use id <- decode.field(0,decode.int) use name <- decode.field(1,decode.string) use province <- decode.field(2,decode.string) decode.success(#(id,name,province)) }
  let data = pog.query("select id,name,province from regions order by name") |> pog.returning(decoder) |> pog.execute(database)
  let html = case data { Ok(rows) -> rows.rows |> list.map(fn(row) { let #(id,name,province)=row "<div class='region-row'><div><b>"<>name<>"</b><small>"<>province<>"</small></div><span>#"<>int.to_string(id)<>"</span></div>" }) |> string.join("") Error(_) -> "<p>Bölgeler yüklenemedi.</p>" }
  html_response(200, html)
}

fn create_region(req: Request(Connection), database: pog.Connection) -> Response(ResponseData) {
  case mist.read_body(req, 32_000) {
    Ok(body_req) -> {
      let fields = body_req.body |> bit_array.to_string |> result.try(uri.parse_query) |> result.unwrap([])
      let name = list.key_find(fields, "name") |> result.unwrap("")
      let province = list.key_find(fields, "province") |> result.unwrap("")
      let image = list.key_find(fields, "image") |> result.unwrap("")
      let slug = name |> string.lowercase |> string.replace(" ", "-")
      let saved = pog.query("insert into regions(name,province,slug,cover_image) values($1,$2,$3,nullif($4,'')) on conflict(slug) do update set name=excluded.name,province=excluded.province,cover_image=excluded.cover_image") |> pog.parameter(pog.text(name)) |> pog.parameter(pog.text(province)) |> pog.parameter(pog.text(slug)) |> pog.parameter(pog.text(image)) |> pog.execute(database)
      case saved { Ok(_) -> admin_regions_fragment(database) Error(_) -> html_response(422,"<p class='htmx-error'>Bölge kaydedilemedi.</p>") }
    }
    Error(_) -> html_response(400,"<p class='htmx-error'>Geçersiz form.</p>")
  }
}

fn listings_fragment(database: pog.Connection) -> Response(ResponseData) {
  let decoder = {
    use id <- decode.field(0, decode.int)
    use title <- decode.field(1, decode.string)
    use region <- decode.field(2, decode.string)
    use price <- decode.field(3, decode.int)
    decode.success(#(id, title, region, price))
  }
  let rows =
    pog.query("select id, title_tr, region_name, price_try::int from published_listings order by featured desc, created_at desc limit 12")
    |> pog.returning(decoder)
    |> pog.execute(database)
  let html = case rows {
    Ok(result) -> result.rows |> list.map(render_listing) |> string.join("")
    Error(_) -> "<p class='htmx-error'>İlanlar şu anda yüklenemiyor.</p>"
  }
  html_response(200, html)
}

fn render_listing(row: #(Int, String, String, Int)) -> String {
  let #(id, title, region, price) = row
  "<article class='property-card'><a href='/ilan.html?id=" <> int.to_string(id) <> "'><div class='property-body'><span class='property-location'>" <> region <> "</span><h3>" <> title <> "</h3><div class='property-price'><b>" <> int.to_string(price) <> " ₺</b></div></div></a></article>"
}

fn admin_stats_fragment(database: pog.Connection) -> Response(ResponseData) {
  let decoder = {
    use total <- decode.field(0, decode.int)
    decode.success(total)
  }
  let count = pog.query("select count(*)::int from listings") |> pog.returning(decoder) |> pog.execute(database)
  let total = case count { Ok(result) -> result.rows |> list.first |> result.unwrap(0) Error(_) -> 0 }
  html_response(200, "<strong>" <> int.to_string(total) <> "</strong><small>TOPLAM İLAN</small>")
}

fn serve(path: String, mime: String) -> Response(ResponseData) {
  let static_root = envoy.get("STATIC_ROOT") |> result.unwrap(".")
  mist.send_file(static_root <> "/" <> path, offset: 0, limit: option.None)
  |> result.map(fn(file) { response.new(200) |> response.set_header("content-type", mime) |> response.set_body(file) })
  |> result.lazy_unwrap(fn() { text_response(404, "Not found") })
}
fn content_type(path: List(String)) -> String { case list.last(path) { Ok(name) -> case string.ends_with(name, ".css") { True -> "text/css" False -> case string.ends_with(name, ".js") { True -> "application/javascript" False -> case string.ends_with(name, ".webp") { True -> "image/webp" False -> case string.ends_with(name, ".png") { True -> "image/png" False -> "application/octet-stream" } } } } Error(_) -> "application/octet-stream" } }
fn html_response(status: Int, body: String) { response.new(status) |> response.set_header("content-type", "text/html; charset=utf-8") |> response.set_body(mist.Bytes(bytes_tree.from_string(body))) }
fn text_response(status: Int, body: String) { response.new(status) |> response.set_header("content-type", "text/plain; charset=utf-8") |> response.set_body(mist.Bytes(bytes_tree.from_string(body))) }
