import envoy
import gleam/bytes_tree
import gleam/dynamic/decode
import gleam/erlang/process
import gleam/http/request.{type Request}
import gleam/http/response.{type Response}
import gleam/int
import gleam/list
import gleam/option
import gleam/otp/static_supervisor as supervisor
import gleam/result
import gleam/string
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
    ["htmx", "admin", "stats"] -> admin_stats_fragment(database)
    [] -> serve("index.html", "text/html; charset=utf-8")
    ["ilan.html"] -> serve("ilan.html", "text/html; charset=utf-8")
    ["admin.html"] -> serve("admin.html", "text/html; charset=utf-8")
    ["satilik.html"] -> serve("satilik.html", "text/html; charset=utf-8")
    ["kiralik.html"] -> serve("kiralik.html", "text/html; charset=utf-8")
    ["bolgeler.html"] -> serve("bolgeler.html", "text/html; charset=utf-8")
    ["hakkimizda.html"] -> serve("hakkimizda.html", "text/html; charset=utf-8")
    ["iletisim.html"] -> serve("iletisim.html", "text/html; charset=utf-8")
    ["kvkk.html"] -> serve("kvkk.html", "text/html; charset=utf-8")
    ["gizlilik.html"] -> serve("gizlilik.html", "text/html; charset=utf-8")
    ["robots.txt"] -> serve("robots.txt", "text/plain; charset=utf-8")
    ["assets", ..rest] -> serve("assets/" <> string.join(rest, "/"), content_type(rest))
    _ -> text_response(404, "Not found")
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
  mist.send_file("public/" <> path, offset: 0, limit: option.None)
  |> result.map(fn(file) { response.new(200) |> response.set_header("content-type", mime) |> response.set_body(file) })
  |> result.lazy_unwrap(fn() { text_response(404, "Not found") })
}
fn content_type(path: List(String)) -> String { case list.last(path) { Ok(name) -> case string.ends_with(name, ".css") { True -> "text/css" False -> case string.ends_with(name, ".js") { True -> "application/javascript" False -> case string.ends_with(name, ".webp") { True -> "image/webp" False -> case string.ends_with(name, ".png") { True -> "image/png" False -> "application/octet-stream" } } } } Error(_) -> "application/octet-stream" } }
fn html_response(status: Int, body: String) { response.new(status) |> response.set_header("content-type", "text/html; charset=utf-8") |> response.set_body(mist.Bytes(bytes_tree.from_string(body))) }
fn text_response(status: Int, body: String) { response.new(status) |> response.set_header("content-type", "text/plain; charset=utf-8") |> response.set_body(mist.Bytes(bytes_tree.from_string(body))) }
