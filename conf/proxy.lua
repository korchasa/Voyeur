local router = require 'router'
local rapidjson = require 'rapidjson'
local r = router.new()

function split(inputstr, sep)
  local t, i = {}, 1
  if null == inputstr then
    return t
  end
  for str in string.gmatch(inputstr, "([^"..sep.."]+)") do
    t[i] = str
    i = i + 1
  end
  return t
end

function ok(data)
  return {
    status = 200,
    data = data,
    error = ''
  }
end

function error(message, code)
  return {
    status = code,
    data = {},
    error = message
  }
end

r:get('/api/v1/jobs_queue', function(params)
  res = ngx.location.capture("/airtableJob?view=Valid&fields%5B%5D=author&fields%5B%5D=kind&fields%5B%5D=start&fields%5B%5D=finish&fields%5B%5D=result&fields%5B%5D=status&fields%5B%5D=callback&fields%5B%5D=params")
  res_data = (rapidjson.decode(res.body))
  if res.status ~= 200 then
    return error(res_data.error.message, 400)
  else
    resp = {}
    for i, record in ipairs(res_data.records) do
      resp[i] = record.fields
    end
    return ok(resp)
  end
end)

r:post('/api/v1/jobs_queue', function(params)

  local req, err = rapidjson.decode(ngx.req.get_body_data())

  if req == nil then req = {} end

  req.status = "new"

  for k, field in ipairs ({"author", "kind", "callback", "params"}) do
    if nil == req[field] then
      return error("'" .. field .. "' field is required", 400)
    end
  end

  res = ngx.location.capture("/airtableJob", {
    method = ngx.HTTP_POST,
    body = rapidjson.encode({fields = req}),
  })

  res_data = (rapidjson.decode(res.body))
  if res.status ~= 200 then
    return error(res_data.error.message, 400)
  else
    return ok({id = res_data.id})
  end

end)

r:put('/api/v1/jobs_queue/:id', function(params)

  local req, err = rapidjson.decode(ngx.req.get_body_data())

  if req == nil then req = {} end

  for k, field in ipairs ({"author", "kind", "callback", "params"}) do
    if nil == req[field] then
      return error("'" .. field .. "' field is required", 400)
    end
  end

  res = ngx.location.capture("/airtableJob/" .. params.id, {
      method = ngx.HTTP_PUT,
      body = rapidjson.encode({ fields = req })
  })

  res_data = (rapidjson.decode(res.body))
  if res.status ~= 200 then
    return error(res_data.error.message, 400)
  else
    return ok({id = params.id})
  end
end)

ngx.header.content_type = 'application/json';
local isRouted, result = r:execute(
  ngx.var.request_method,
  ngx.var.request_uri,
  ngx.req.get_uri_args(),
  ngx.req.get_post_args()
)

if true ~= isRouted then
  result = error(result, 404)
end

ngx.status = result.status

if 400 > result.status then
  ngx.print(rapidjson.encode(result, {pretty = true}))
else
  ngx.print(rapidjson.encode({
    status = result.status,
    error = result.error
  }, {pretty = true}))
  ngx.log(ngx.ERR, result.error)
end