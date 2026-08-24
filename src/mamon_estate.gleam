import envoy
import gleam/bytes_tree
import gleam/erlang/process
import gleam/http/request.{type Request}
import gleam/http/response.{type Response}
import gleam/int
import gleam/list
import gleam/option
import gleam/result
import gleam/string
import mist.{type Connection, type ResponseData}

pub fn main() {
  let port =
    envoy.get("PORT") |> result.try(int.parse) |> result.unwrap(8080)

  let assert Ok(_) =
    fn(req: Request(Connection)) { router(req) }
    |> mist.new
    |> mist.bind("0.0.0.0")
    |> mist.port(port)
    |> mist.start

  process.sleep_forever()
}

fn router(req: Request(Connection)) -> Response(ResponseData) {
  case request.path_segments(req) {
    ["health"] -> text_response(200, "ok")
    // Static HTML pages
    [] -> serve("index.html", "text/html; charset=utf-8")
    ["ilan.html"] -> serve("ilan.html", "text/html; charset=utf-8")
    ["ilan", _id] -> serve("ilan.html", "text/html; charset=utf-8")
    ["satilik.html"] -> serve("satilik.html", "text/html; charset=utf-8")
    ["kiralik.html"] -> serve("kiralik.html", "text/html; charset=utf-8")
    ["bolgeler.html"] -> serve("bolgeler.html", "text/html; charset=utf-8")
    ["hakkimizda.html"] -> serve("hakkimizda.html", "text/html; charset=utf-8")
    ["iletisim.html"] -> serve("iletisim.html", "text/html; charset=utf-8")
    ["kvkk.html"] -> serve("kvkk.html", "text/html; charset=utf-8")
    ["gizlilik.html"] -> serve("gizlilik.html", "text/html; charset=utf-8")
    ["uye-girisi.html"] -> serve("uye-girisi.html", "text/html; charset=utf-8")
    ["uye-ol.html"] -> serve("uye-ol.html", "text/html; charset=utf-8")
    ["sifremi-unuttum.html"] -> serve("sifremi-unuttum.html", "text/html; charset=utf-8")
    ["sifre-yenile.html"] -> serve("sifre-yenile.html", "text/html; charset=utf-8")
    // Clean URLs → HTML files
    ["satilik"] -> serve("satilik.html", "text/html; charset=utf-8")
    ["kiralik"] -> serve("kiralik.html", "text/html; charset=utf-8")
    ["bolgeler"] -> serve("bolgeler.html", "text/html; charset=utf-8")
    ["hakkimizda"] -> serve("hakkimizda.html", "text/html; charset=utf-8")
    ["iletisim"] -> serve("iletisim.html", "text/html; charset=utf-8")
    ["kvkk"] -> serve("kvkk.html", "text/html; charset=utf-8")
    ["gizlilik"] -> serve("gizlilik.html", "text/html; charset=utf-8")
    ["uye-girisi"] -> serve("uye-girisi.html", "text/html; charset=utf-8")
    ["uye-ol"] -> serve("uye-ol.html", "text/html; charset=utf-8")
    ["sifremi-unuttum"] -> serve("sifremi-unuttum.html", "text/html; charset=utf-8")
    ["sifre-yenile"] -> serve("sifre-yenile.html", "text/html; charset=utf-8")
    // Static files
    ["robots.txt"] -> serve("robots.txt", "text/plain; charset=utf-8")
    ["assets", ..rest] -> serve("assets/" <> string.join(rest, "/"), content_type(rest))
    // 404
    _ -> text_response(404, "Not found")
  }
}

/// ── Response helpers ──────────────────────────────────────────────────
fn serve(path: String, mime: String) -> Response(ResponseData) {
  let static_root = envoy.get("STATIC_ROOT") |> result.unwrap(".")
  mist.send_file(static_root <> "/" <> path, offset: 0, limit: option.None)
  |> result.map(fn(file) {
    response.new(200)
    |> response.set_header("content-type", mime)
    |> response.set_body(file)
  })
  |> result.lazy_unwrap(fn() { text_response(404, "Not found") })
}

fn content_type(path: List(String)) -> String {
  case list.last(path) {
    Ok(name) -> {
      case string.ends_with(name, ".css") {
        True -> "text/css"
        False ->
          case string.ends_with(name, ".js") {
            True -> "application/javascript"
            False ->
              case string.ends_with(name, ".webp") {
                True -> "image/webp"
                False ->
                  case string.ends_with(name, ".png") {
                    True -> "image/png"
                    False -> "application/octet-stream"
                  }
              }
          }
      }
    }
    Error(_) -> "application/octet-stream"
  }
}

fn text_response(status: Int, body: String) -> Response(ResponseData) {
  response.new(status)
  |> response.set_header("content-type", "text/plain; charset=utf-8")
  |> response.set_body(mist.Bytes(bytes_tree.from_string(body)))
}
